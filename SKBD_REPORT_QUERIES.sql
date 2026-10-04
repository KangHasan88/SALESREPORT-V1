/*
  SKBD Report - Query Documentation
  Path app: /home/itsec/skbd-report
  Route: /report/skbd

  IMPORTANT:
  - Semua query aplikasi bersifat SELECT/read-only.
  - Koneksi aplikasi menggunakan user skbd_report_ro.
  - Session PDO dipaksa read-only:
      SET default_transaction_read_only = on;
      SET SESSION CHARACTERISTICS AS TRANSACTION READ ONLY;
      SET TRANSACTION READ ONLY;

  Parameter Laravel/PDO:
  - :start_date  format YYYY-MM-DD
  - :end_date    format YYYY-MM-DD
  - :search      optional, format lower wildcard: %keyword%
  - :status      optional, integer Status Order
  - :amount_min  optional, numeric batas bawah total nilai SO
  - :amount_max  optional, numeric batas atas total nilai SO
  - :limit_rows  pagination limit
  - :offset_rows pagination offset

  Status Order mengikuti ERP:
  - 0 = Open
  - 1 = Completed
  - 2 = Partial
  - 3 = Canceled
  - 4 = In Process
  - 5 = Closed
*/

begin transaction read only;

/*
  1. Count rows untuk pagination.

  Filter optional:
  - Jika search diisi, tambahkan:
      and (lower(m.so_no) like :search or lower(coalesce(p.name, '')) like :search)
  - Jika status dipilih, tambahkan:
      and m.order_status = :status
  - Jika range nilai diisi, tambahkan:
      and coalesce(m.total_price, 0) >= :amount_min
      and coalesce(m.total_price, 0) <= :amount_max
*/
select count(*)
from public.tb_salesorder_m m
left join public.tb_m_people p on p.id_people = m.customer_id
where m.so_date::date between :start_date and :end_date;

/*
  2. Summary cards.

  Filter optional sama seperti query count.
*/
select
    count(*) as total_so,
    count(distinct m.customer_id) as total_customer,
    coalesce(sum(m.total_price), 0) as total_amount,
    max(m.so_date) as latest_so_date
from public.tb_salesorder_m m
left join public.tb_m_people p on p.id_people = m.customer_id
left join public.tb_m_slsman s on s.slsman_id = m.rep_id
where m.so_date::date between :start_date and :end_date;

/*
  3. Main report data.

  Notes:
  - Salesman mengikuti ERP:
      customer -> tb_m_customersdata.slsgrp_id -> tb_m_slsmanarea -> tb_m_slsman.
      Jika mapping customer kosong, fallback ke tb_salesorder_m.rep_id.
  - Channel:
      customer -> tb_m_customersdata.id_cg -> tb_m_custgrp.
  - Total KG dan KG detail:
      MC = so_qty * external_weight/internal_weight.
      IC = qty_unit * weight_per_inner, dengan konversi:
          Lbs/Lb -> * 0.45359237
          Gr/Gram dan weight_per_inner >= 50 -> / 1000
          lainnya diasumsikan KG.

  Filter optional:
  - Jika search diisi, tambahkan:
      and (lower(m.so_no) like :search or lower(coalesce(p.name, '')) like :search)
  - Jika status dipilih, tambahkan:
      and m.order_status = :status
*/
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
    coalesce(nullif(trim(m.no_op), ''), '-') as no_op,
    coalesce(nullif(trim(m.currency_cd), ''), '-') as currency_cd,
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
where m.so_date::date between :start_date and :end_date
order by m.so_date desc, m.so_no desc
limit :limit_rows offset :offset_rows;

commit;
