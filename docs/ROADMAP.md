```bash
# 1) Create DB (once)
mysql -u <user> -p -e "CREATE DATABASE IF NOT EXISTS lab_scheduler CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;"

# 2) Run migrations (in order)
mysql -u <user> -p lab_scheduler < db/migrations/001_init_mysql.sql
mysql -u <user> -p lab_scheduler < db/migrations/002_seed_mysql.sql
```
