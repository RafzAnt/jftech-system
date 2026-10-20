-- The overtime QR code belongs to a branch: each branch admin makes the one of their branch, on the days that branch approves overtime,
-- and only that branch's employees can use it. The morning and afternoon codes stay shared (no branch).
-- Run this once in the Supabase SQL Editor; running it again changes nothing.

begin;

alter table public.qr_codes add column if not exists branch_name varchar(100);

commit;
