-- One-time compatibility repair for the Laravel codebase's archived = 0/1 queries.
-- Run this once in Supabase SQL Editor after schema.sql has already been run.
do $$
declare
    table_name text;
begin
    foreach table_name in array array['users', 'clients', 'internet_plans', 'inventory', 'payroll']
    loop
        execute format('alter table public.%I alter column archived drop default', table_name);
        execute format('alter table public.%I alter column archived type smallint using case when archived then 1 else 0 end', table_name);
        execute format('alter table public.%I alter column archived set default 0', table_name);
    end loop;
end $$;
