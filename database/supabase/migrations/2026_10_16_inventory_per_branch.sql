-- Per-branch stock. Apply once in the Supabase SQL Editor for an existing project.
-- Safe to rerun. Does not delete any rows.
--
--   * inventory.branch_name   the branch that holds the stock (existing items stay with 'Balilihan')
--   * the item name is now unique per branch instead of company-wide, so two branches can stock a Router
BEGIN;

ALTER TABLE public.inventory
    ADD COLUMN IF NOT EXISTS branch_name varchar(100) NOT NULL DEFAULT 'Balilihan';

-- Drop the old company-wide unique (item_name, category), whatever Postgres named it.
DO $$
DECLARE
    existing record;
BEGIN
    FOR existing IN
        SELECT c.conname
        FROM pg_constraint c
        WHERE c.conrelid = 'public.inventory'::regclass
          AND c.contype = 'u'
          AND (
              SELECT array_agg(a.attname::text ORDER BY a.attname)
              FROM unnest(c.conkey) AS k(attnum)
              JOIN pg_attribute a ON a.attrelid = c.conrelid AND a.attnum = k.attnum
          ) = ARRAY['category', 'item_name']
    LOOP
        EXECUTE format('ALTER TABLE public.inventory DROP CONSTRAINT %I', existing.conname);
    END LOOP;
END
$$;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conrelid = 'public.inventory'::regclass AND conname = 'inventory_branch_item_category_key'
    ) THEN
        ALTER TABLE public.inventory
            ADD CONSTRAINT inventory_branch_item_category_key UNIQUE (branch_name, item_name, category);
    END IF;
END
$$;

COMMIT;
