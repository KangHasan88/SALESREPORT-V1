/*
  SKBD Report - Navicat Runnable Queries

  Cara pakai:
  1. Ubah nilai di CTE params.
  2. Jalankan query yang dibutuhkan.
  3. Semua query di file ini SELECT/read-only.

  Status Order ERP:
  0 = Open
  1 = Completed
  2 = Partial
  3 = Canceled
  4 = In Process
  5 = Closed
*/

-- =========================================================
-- 1. Summary Cards
-- =========================================================
with params as (
    select
        date '2026-08-01' as start_date,
        date '2026-08-06' as end_date,
        lower('%Customer Sample%')::text as search_text,
        null::int as status_order,
        null::numeric as amount_min,
        null::numeric as amount_max
)
select
    count(*) as total_so,
    count(distinct m.customer_id) as total_customer,
    coalesce(sum(m.total_price), 0) as total_amount,
    max(m.so_date) as latest_so_date
from public.tb_salesorder_m m
left join public.tb_m_people p on p.id_people = m.customer_id
cross join params prm
where m.so_date::date between prm.start_date and prm.end_date
  and (
      prm.search_text is null
      or lower(m.so_no) like prm.search_text
      or lower(coalesce(p.name, '')) like prm.search_text
  )
  and (
      prm.status_order is null
      or m.order_status = prm.status_order
  )
  and (
      prm.amount_min is null
      or coalesce(m.total_price, 0) >= prm.amount_min
  )
  and (
      prm.amount_max is null
      or coalesce(m.total_price, 0) <= prm.amount_max
  );

-- =========================================================
-- 2. Main Report + Detail SO JSON
-- =========================================================
with params as (
    select
        date '2026-08-01' as start_date,
        date '2026-08-06' as end_date,
        lower('%Customer Sample%')::text as search_text,
        null::int as status_order,
        null::numeric as amount_min,
        null::numeric as amount_max,
        10::int as limit_rows,
        0::int as offset_rows
)
select
    m.so_no,
    m.so_date,
    coalesce(nullif(trim(p.name), ''), '-') as customer_name,
    coalesce(
        nullif(trim(concat_ws(' ', m.txt_tmpdlvaddr, m.txt_tmpdlvcity)), ''),
        nullif(trim(concat_ws(' ', p.address, p.city)), ''),
        '-'
    ) as shipping_address,
    coalesce(m.total_price, 0) as amount,
    coalesce(items.total_kg, 0) as total_kg,
    m.order_status,
    case m.order_status
        when 0 then 'Open'
        when 1 then 'Completed'
        when 2 then 'Partial'
        when 3 then 'Canceled'
        when 4 then 'In Process'
        when 5 then 'Closed'
        else 'Status ' || m.order_status::text
    end as status_order_label,
    coalesce(nullif(trim(m.user_id), ''), '-') as user_id,
    coalesce(nullif(trim(concat_ws('-', cg.name_cg, cg.desc_cg)), ''), '-') as channel_name,
    coalesce(customer_salesman.slsman_id, s.slsman_id, 0) as salesman_id,
    coalesce(
        nullif(trim(customer_salesman.slsman_nm), ''),
        nullif(trim(s.slsman_nm), ''),
        '-'
    ) as salesman_name,
    coalesce(items.items_json, '[]') as items_json
from public.tb_salesorder_m m
left join public.tb_m_people p on p.id_people = m.customer_id
left join public.tb_m_slsman s on s.slsman_id = m.rep_id
left join public.tb_m_customersdata cd on cd.id_people = m.customer_id
left join public.tb_m_custgrp cg on cg.id_cg = cd.id_cg
left join lateral (
    select sm.slsman_id, sm.slsman_nm
    from public.tb_m_slsmanarea area
    join public.tb_m_slsman sm on sm.slsman_id = area.slsman_id
    where area.slsgrp_id = cd.slsgrp_id
      and coalesce(sm.slsman_state, 0) = 1
    order by
        case when sm.slsmanpos_cd = 'SS' then 0 else 1 end,
        sm.slsman_id
    limit 1
) customer_salesman on true
left join lateral (
    select
        json_agg(
            json_build_object(
                'sku', coalesce(nullif(trim(i.code), ''), d.id_barang::text),
                'name', coalesce(
                    nullif(trim(coalesce(pr.product_name, pr_alt.product_name)), ''),
                    nullif(trim(d.ext_productname), ''),
                    nullif(trim(i.item_name), ''),
                    '-'
                ),
                'brand', coalesce(
                    nullif(trim(coalesce(pr.brand, pr_alt.brand)), ''),
                    case
                        when coalesce(pr.product_name, pr_alt.product_name, d.ext_productname, i.item_name, '') ~* '^BF[[:space:]]' then 'BUMIFOOD'
                        else null
                    end,
                    '-'
                ),
                'qty', case
                    when d.id_measure = 1 and coalesce(d.qty_unit, 0) > 0 then d.qty_unit
                    else coalesce(d.so_qty, d.qty_unit, 0)
                end,
                'unit', case
                    when d.id_measure = 4 then 'MC'
                    when d.id_measure = 1 then 'IC'
                    else coalesce(nullif(trim(me.symbol), ''), '')
                end,
                'kg', case
                    when d.id_measure = 1 and coalesce(pr.weight_per_inner, pr_alt.weight_per_inner, 0) > 0 then
                        coalesce(d.qty_unit, 0) *
                        case
                            when coalesce(pr.product_name, pr_alt.product_name, '') ~* 'lbs|lb' then coalesce(pr.weight_per_inner, pr_alt.weight_per_inner) * 0.45359237
                            when coalesce(pr.product_name, pr_alt.product_name, '') ~* 'gr|gram' and coalesce(pr.weight_per_inner, pr_alt.weight_per_inner) >= 50 then coalesce(pr.weight_per_inner, pr_alt.weight_per_inner) / 1000
                            else coalesce(pr.weight_per_inner, pr_alt.weight_per_inner)
                        end
                    else coalesce(d.so_qty, 0) * coalesce(pr.external_weight, pr_alt.external_weight, pr.internal_weight, pr_alt.internal_weight, 0)
                end,
                'amount', coalesce(d.sub_total, d.so_qty * d.unit_price, 0)
            )
            order by d.no_seq
        ) as items_json,
        sum(
            case
                when d.id_measure = 1 and coalesce(pr.weight_per_inner, pr_alt.weight_per_inner, 0) > 0 then
                    coalesce(d.qty_unit, 0) *
                    case
                        when coalesce(pr.product_name, pr_alt.product_name, '') ~* 'lbs|lb' then coalesce(pr.weight_per_inner, pr_alt.weight_per_inner) * 0.45359237
                        when coalesce(pr.product_name, pr_alt.product_name, '') ~* 'gr|gram' and coalesce(pr.weight_per_inner, pr_alt.weight_per_inner) >= 50 then coalesce(pr.weight_per_inner, pr_alt.weight_per_inner) / 1000
                        else coalesce(pr.weight_per_inner, pr_alt.weight_per_inner)
                    end
                else coalesce(d.so_qty, 0) * coalesce(pr.external_weight, pr_alt.external_weight, pr.internal_weight, pr_alt.internal_weight, 0)
            end
        ) as total_kg
    from public.tb_salesorder_d_item d
    left join public.tb_product pr on pr.id_product = d.id_barang
    left join public.tb_product pr_alt on pr_alt.id_product = d.id_barang + 10000
    left join public.tb_items i on i.id_items = d.id_barang
    left join public.tb_measure me on me.id_measure = d.id_measure
    where d.so_id = m.so_id
) items on true
cross join params prm
where m.so_date::date between prm.start_date and prm.end_date
  and (
      prm.search_text is null
      or lower(m.so_no) like prm.search_text
      or lower(coalesce(p.name, '')) like prm.search_text
  )
  and (
      prm.status_order is null
      or m.order_status = prm.status_order
  )
  and (
      prm.amount_min is null
      or coalesce(m.total_price, 0) >= prm.amount_min
  )
  and (
      prm.amount_max is null
      or coalesce(m.total_price, 0) <= prm.amount_max
  )
order by m.so_date desc, m.so_no desc
limit (select limit_rows from params)
offset (select offset_rows from params);

-- =========================================================
-- 3. Detail SO flat table, mudah dibaca di Navicat
-- =========================================================
with params as (
    select 'CL-2608-00106-1'::text as so_no
)
select
    d.no_seq as no,
    coalesce(nullif(trim(i.code), ''), d.id_barang::text) as sku,
    coalesce(
        nullif(trim(coalesce(pr.product_name, pr_alt.product_name)), ''),
        nullif(trim(d.ext_productname), ''),
        nullif(trim(i.item_name), ''),
        '-'
    ) as nama_item,
    coalesce(
        nullif(trim(coalesce(pr.brand, pr_alt.brand)), ''),
        case
            when coalesce(pr.product_name, pr_alt.product_name, d.ext_productname, i.item_name, '') ~* '^BF[[:space:]]' then 'BUMIFOOD'
            else null
        end,
        '-'
    ) as brand,
    case
        when d.id_measure = 1 and coalesce(d.qty_unit, 0) > 0 then d.qty_unit
        else coalesce(d.so_qty, d.qty_unit, 0)
    end as qty,
    case
        when d.id_measure = 4 then 'MC'
        when d.id_measure = 1 then 'IC'
        else coalesce(nullif(trim(me.symbol), ''), '')
    end as satuan,
    case
        when d.id_measure = 1 and coalesce(pr.weight_per_inner, pr_alt.weight_per_inner, 0) > 0 then
            coalesce(d.qty_unit, 0) *
            case
                when coalesce(pr.product_name, pr_alt.product_name, '') ~* 'lbs|lb' then coalesce(pr.weight_per_inner, pr_alt.weight_per_inner) * 0.45359237
                when coalesce(pr.product_name, pr_alt.product_name, '') ~* 'gr|gram' and coalesce(pr.weight_per_inner, pr_alt.weight_per_inner) >= 50 then coalesce(pr.weight_per_inner, pr_alt.weight_per_inner) / 1000
                else coalesce(pr.weight_per_inner, pr_alt.weight_per_inner)
            end
        else coalesce(d.so_qty, 0) * coalesce(pr.external_weight, pr_alt.external_weight, pr.internal_weight, pr_alt.internal_weight, 0)
    end as kg,
    coalesce(d.sub_total, d.so_qty * d.unit_price, 0) as nilai
from public.tb_salesorder_d_item d
left join public.tb_product pr on pr.id_product = d.id_barang
left join public.tb_product pr_alt on pr_alt.id_product = d.id_barang + 10000
left join public.tb_items i on i.id_items = d.id_barang
left join public.tb_measure me on me.id_measure = d.id_measure
cross join params prm
where d.so_no = prm.so_no
order by d.no_seq;
