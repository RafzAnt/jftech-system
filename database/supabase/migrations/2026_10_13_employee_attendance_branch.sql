-- Employees belong to a branch; attendance keeps the branch recorded on the day.
ALTER TABLE public.employees
    ADD COLUMN IF NOT EXISTS branch_name varchar(100) NOT NULL DEFAULT 'Balilihan';

ALTER TABLE public.attendance
    ADD COLUMN IF NOT EXISTS branch_name varchar(100);
