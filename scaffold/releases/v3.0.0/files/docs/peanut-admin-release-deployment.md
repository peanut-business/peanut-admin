# Deployment

One deployment is one application instance with its own database, secrets, file storage and lifecycle. Use the checked-in Compose and Docker sources only after registering this application's resources; never inherit the scaffold source environment.

`server/database/init.sql` together with Core `KernelSchema` is the complete canonical fresh baseline. A generated 3.0 application installs only into an empty database; application database upgrades and an application migration ledger are not supported. Plugin Module migrations keep their independent lifecycle. Multi-tenant deployments require a separate PlatformOperator identity and the `/platform/` bundle.
