# Deployment

One deployment is one application instance with its own database, secrets, file storage and lifecycle. Use the checked-in Compose and Docker sources only after registering this application's resources; never inherit the scaffold source environment.

`server/database/init.sql` is the canonical fresh baseline. `server/database/migrations/` contains only additive changes after that baseline. A generated 2.0 application installs into an empty database and does not support legacy adoption or scaffold upgrade. Multi-tenant deployments require a separate PlatformOperator identity and the `/platform/` bundle.
