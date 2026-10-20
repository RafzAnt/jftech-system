-- Inventory and NAP boxes work as one: a stock transaction says which employee handled it and, when it belongs to a job, which
-- client and NAP box it was for. Installing a NAP box takes a unit of the "NAP Box" stock item out of the branch's stock.
-- Run this once in the Supabase SQL Editor; running it again changes nothing.

begin;

alter table public.inventory_stock_movements
    add column if not exists employee_id bigint references public.employees(id) on delete set null,
    add column if not exists client_id bigint references public.clients(id) on delete set null,
    add column if not exists nap_box_id bigint references public.nap_boxes(id) on delete set null,
    add column if not exists work_request_id bigint references public.work_requests(id) on delete set null;

create index if not exists inventory_stock_movements_nap_box_index on public.inventory_stock_movements (nap_box_id);

commit;
