# Deployment

One deployment is one application instance with its own database, secrets, file storage and lifecycle. Use the checked-in Compose and Docker sources only after registering this application's resources; never inherit the scaffold source environment.

`server/database/init.sql` together with Core `KernelSchema` is the complete canonical fresh baseline. Scaffold Product Token 3.0 is fresh-only across the major-version boundary; normal patch/minor updates preserve data, install locked dependencies, and apply append-only `server/database/migrations/*.sql` through `php server/database/install.php --migrate --target-version=X.Y.Z`. A newer major release must use the explicit, backed-up `--fresh` path. Plugin Module migrations keep their independent lifecycle. Multi-tenant deployments require a separate PlatformOperator identity and the `/platform/` bundle.
