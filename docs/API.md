# API documentation

Base URL: `http://127.0.0.1:8000/api`. Use `Accept: application/json` and JSON request bodies. For Postman, provide `device_name` in login and use the returned token with `Authorization: Bearer <token>`. The browser uses same-origin session and CSRF instead. Status codes: 200 success, 201 created, 401 unauthenticated, 403 forbidden, 404 unavailable/not owned, 422 validation or state error, 429 rate limited. No real payment credentials.

Import `ChefLagbe.postman_collection.json` into Postman. Run a relevant role login request first; it automatically stores the bearer token. Change `booking_date` to a future date, and set IDs to records in your own development database. The requests are examples, not a fully ordered test suite.

## Authentication

| Method | Endpoint | Request body |
|---|---|---|
| POST | `/auth/signup` | `first_name, last_name, email, phone, location, password, password_confirmation, user_type, terms, device_name` |
| POST | `/auth/login` | `email, password, device_name` |
| GET | `/auth/me` | — |
| POST | `/auth/resend-verification` | — (authenticated; throttled) |
| POST | `/auth/logout` | — |
| POST | `/auth/forgot-password` | `email` |
| POST | `/auth/reset-password` | `email, token, password, password_confirmation` |

## Public

| Method | Endpoint | Request body |
|---|---|---|
| GET | `/chefs?location=Dhaka&per_page=6` | — |
| GET | `/chefs/{{chef_id}}` | — |
| GET | `/chefs/{{chef_id}}/reviews` | — |
| GET | `/chefs/{{chef_id}}/availability?booking_date={{booking_date}}&booking_time=11:00&duration_hours=3` | — |
| GET | `/services` | — |
| GET | `/services/1` | — |
| GET | `/public/stats` | — |
| POST | `/contact` | `first_name, last_name, email, subject, message` |

## Customer and bookings

| Method | Endpoint | Request body |
|---|---|---|
| GET | `/users/profile` | — |
| PUT | `/users/profile` | `first_name, last_name, phone, location` |
| PUT | `/users/change-password` | `current_password, password, password_confirmation` |
| POST | `/users/upload-avatar` | multipart:avatar |
| GET | `/bookings` | — |
| POST | `/bookings` | `chef_id, service_id, booking_date, booking_time, duration_hours, guests, special_instructions` |
| GET | `/bookings/{{booking_id}}` | — |
| PUT | `/bookings/{{booking_id}}` | `status` |
| DELETE | `/bookings/{{booking_id}}` | — |
| GET | `/bookings/availability?chef_id={{chef_id}}&booking_date={{booking_date}}&booking_time=11:00&duration_hours=3` | — |
| POST | `/payments` | `booking_id, method, outcome` |
| POST | `/payments/verify` | `transaction_id` |
| GET | `/payments/{{booking_id}}` | — |
| POST | `/reviews` | `booking_id, rating, comment` |
| GET | `/reviews/my` | — |

## Chef

| Method | Endpoint | Request body |
|---|---|---|
| POST | `/auth/login` | `email, password, device_name` |
| GET | `/chef/profile` | — |
| PUT | `/chef/profile` | `specialty, bio, price_per_hour, experience_years` |
| PUT | `/chef/availability` | `availability` |
| POST | `/chefs/apply` | multipart:profile_image,verification_docs |
| GET | `/chef/earnings` | — |
| GET | `/bookings` | — |
| PUT | `/bookings/{{booking_id}}` | `status` |

## Admin

| Method | Endpoint | Request body |
|---|---|---|
| POST | `/admin/login` | `email, password, device_name` |
| GET | `/admin/stats` | — |
| GET | `/admin/users` | — |
| PUT | `/admin/users/{{user_id}}/status` | `is_active` |
| GET | `/admin/chefs` | — |
| PUT | `/admin/chefs/{{chef_id}}/verify` | `is_verified` |
| GET | `/admin/chefs/{{chef_id}}/documents` | — |
| GET | `/admin/bookings` | — |
| PUT | `/admin/bookings/{{booking_id}}/status` | `status` |
| GET | `/admin/payments` | — |
| PUT | `/admin/payments/{{payment_id}}/refund` | — |
| GET | `/admin/reviews` | — |
| DELETE | `/admin/reviews/{{review_id}}` | — |
| GET | `/admin/recent-activity` | — |
| GET | `/admin/reports/revenue` | — |
| GET | `/admin/contacts` | — |
| PUT | `/admin/contacts/{{contact_id}}/read` | — |

### Booking behavior
Payment rechecks the slot, chef status and pending request. Repeating a successful payment returns its existing transaction. Confirming a request cancels overlapping pending requests and refunds their successful simulated payments. `availability: {}` pauses future bookings. Run `php artisan cheflagbe:expire-pending` to expire stale pending requests.

Recent activity is derived from booking records, not a separate audit log.
