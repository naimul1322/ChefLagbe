# ChefLagbe database relationship diagram

```mermaid
erDiagram
 USERS ||--o| CHEFS : "chef account"
 USERS ||--o{ BOOKINGS : "customer creates"
 CHEFS ||--o{ BOOKINGS : receives
 SERVICES ||--o{ BOOKINGS : selected_for
 BOOKINGS ||--o{ PAYMENTS : attempts
 BOOKINGS ||--o| REVIEWS : receives
 USERS ||--o{ REVIEWS : writes
 CHEFS ||--o{ REVIEWS : rated_by
 USERS ||--o{ PERSONAL_ACCESS_TOKENS : authenticates
 USERS ||--o| PASSWORD_RESET_TOKENS : "email reset"
 USERS { bigint id PK string email UK string user_type boolean is_active }
 CHEFS { bigint id PK bigint user_id FK decimal price_per_hour decimal rating json availability boolean is_verified }
 SERVICES { bigint id PK string name UK decimal base_price }
 BOOKINGS { bigint id PK string booking_ref UK bigint customer_id FK bigint chef_id FK bigint service_id FK date booking_date time booking_time string status decimal total_amount }
 PAYMENTS { bigint id PK bigint booking_id FK string transaction_id UK decimal amount string status }
 REVIEWS { bigint id PK bigint booking_id UK bigint customer_id FK bigint chef_id FK integer rating }
 CONTACTS { bigint id PK string email string subject text message boolean is_read }
 PASSWORD_RESET_TOKENS { string email PK string token }
 PERSONAL_ACCESS_TOKENS { bigint id PK bigint tokenable_id string token UK }
```

Contacts are independent inquiries. Password reset tokens use email as an application-level relationship rather than a foreign key. Sanctum tokens use polymorphic owner columns. Money uses decimal columns. The migrations are authoritative; use MySQL Workbench's reverse-engineering feature after migration to generate a physical schema diagram.
