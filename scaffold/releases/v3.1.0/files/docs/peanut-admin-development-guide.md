# Development guide

Core owns generic identity, tenancy and authorization contracts. This application owns routes, product settings, pages and business Runtime. A Module owns its tables, use cases, permissions, menu contributions and public DTO/command contracts.

Develop a vertical slice through route, controller, application service and Module contract. Supply TenantContext from trusted middleware; never accept a client-supplied Tenant ID as authorization. Add a normal Tenant A case and a denied Tenant B case before enabling the Module.
