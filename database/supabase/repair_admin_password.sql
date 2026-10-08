-- One-time repair for the initial admin account created with pgcrypto.
-- Laravel requires a $2y$ bcrypt hash; this hash is for password: admin123.
update public.users
set password = '$2y$12$nlNmKVZzUCrvRyopjC9VmePzIetArCFrKaTA7FIn6OVjzINOiWQ8S'
where username = 'admin';
