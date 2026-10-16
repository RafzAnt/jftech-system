-- Super admin + branch admins. Apply once in the Supabase SQL Editor for an existing project.
-- Safe to rerun. Does not delete any rows.
--
--   * users.branch_name       the branch a branch admin works for (empty for super admins and employees)
--   * users.role              now also allows 'super_admin' (sees every branch); 'admin' becomes a branch admin
--   * employees/attendance    branch_name columns, in case an older project does not have them yet
--
-- Every admin that exists today already had access to everything, so they become super admins.
-- That promotion only happens while the project has no super admin yet, so rerunning this file
-- later can never turn a branch admin into a super admin.
BEGIN;

ALTER TABLE public.users
    ADD COLUMN IF NOT EXISTS branch_name varchar(100);

ALTER TABLE public.employees
    ADD COLUMN IF NOT EXISTS branch_name varchar(100) NOT NULL DEFAULT 'Balilihan';

ALTER TABLE public.attendance
    ADD COLUMN IF NOT EXISTS branch_name varchar(100);

-- Replace the old role check (admin/employee) with one that includes super_admin.
DO $$
DECLARE
    existing record;
BEGIN
    FOR existing IN
        SELECT conname
        FROM pg_constraint
        WHERE conrelid = 'public.users'::regclass
          AND contype = 'c'
          AND pg_get_constraintdef(oid) ILIKE '%role%'
    LOOP
        EXECUTE format('ALTER TABLE public.users DROP CONSTRAINT %I', existing.conname);
    END LOOP;
END
$$;

ALTER TABLE public.users
    ADD CONSTRAINT users_role_check CHECK (role IN ('super_admin', 'admin', 'employee'));

UPDATE public.users
SET role = 'super_admin'
WHERE role = 'admin'
  AND NOT EXISTS (SELECT 1 FROM public.users existing_super WHERE existing_super.role = 'super_admin');

COMMIT;
