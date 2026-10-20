# Login protection

`app/auth/login.php` binds the email to a prepared query and checks the stored
password hash with `password_verify()`. Passwords are never included in responses.
The existing login layout and admin/customer redirects are retained.

The server allows five attempts per email address and twenty attempts per IP
address within fifteen minutes. Reservations happen before password verification
and are protected by a file lock, including concurrent requests and new sessions.
Successful login clears the email counter; it does not reset the IP budget.
Blocked requests return HTTP 429 and a `Retry-After` header. Invalid form tokens
return HTTP 400. The session ID is regenerated on successful login.

`includes/login_throttle.php` creates `config/login-attempts.json` on the first
valid form submission. The PHP process must be able to create and update this
file. No database migration or extra credentials are required. The existing
Apache rules deny public access to `config/`, and Git ignores the counter file.
Counters contain hashed identifiers and expire automatically. If the store is
unavailable or corrupt, authentication returns HTTP 503 rather than bypassing
the protection. Diagnose the file permissions or contents before retrying.

These limits suit the current single-server hosting. They can temporarily block
legitimate users sharing an account or IP. Multiple application servers require
a shared database or cache for the counters. Do not trust a client-supplied
forwarded IP header as a substitute for `REMOTE_ADDR`.

Run the isolated checks with `php tests/login_security_test.php`. These tests
use temporary files and dummy users, and do not read hosting credentials.
