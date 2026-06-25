docker compose build
docker compose up -d
docker compose exec app php artisan migrate

## Vani Setu login

Default bootstrap admin credentials:

- Username / employee ID: `ADM-001`
- Password: `admin123`

The login API expects `employee_id` and `password`. These defaults can be
overridden with `BOOTSTRAP_ADMIN_EMPLOYEE_ID`, `BOOTSTRAP_ADMIN_EMAIL`, and
`BOOTSTRAP_ADMIN_PASSWORD` before running the seeders.
