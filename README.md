# Flight Data
Application to manage fligths, aircraft, crew, and related topics.

# Other examples
- [Bytron Aviation Systems](https://www.bytron.aero)
- [AV PLAN EFB](https://www.avplan-efb.com/)

# Requirements
- Support multiple tenants.
- Base application to manage clients.
- Support API to serve mobile application services.

# Dependencies
- [Tenancy for Laravel](https://tenancyforlaravel.com/)
- TailwindCSS
- Laravel Livewire

# Pages

## Base application
- [ ] Landing page
- [ ] Login
- [ ] My Account
### Admin
- [ ] Clients
- [ ] Log in as client

## Tenants Application
- [ ] Users  <!-- We could rename this to "Person"
- [ ] Roles
- [ ] Aircraft
- [ ] Flights 
- [ ] Legs
- [ ] Crew <!-- included in Person 
- [ ] Passangers <!-- included Person

## About
- Flights: This model will be a core element of the app, accessed across multiple modules such as planning, scheduling, flight logs, and technical operations. It will support users with different roles and permissions, ensuring that each role has the appropriate privileges to view or edit relevant data. Depending on the administrator’s configuration, a flight can be created at various stages—starting from scheduling, flight plan or directly at the final stage with the pilot’s log. Also it can be accessible to many Roles, and each role will have certain privileges to view/edit their data. This design provides the flexibility to accommodate both fully planned operations and ad-hoc or emergency flights, where there is no time or no need to create a detailed plan in advance.

- Legs: One flight might have multiple legs with origin, destination, block and flight start and end.

- Person: Since all individuals registered in the app (users, passengers, etc.) share common data that needs to be recorded, a single model will store this information. By linking it to the Role model, each entity can be completed with its specific attributes and permissions. This approach makes it straightforward without duplicated structure.