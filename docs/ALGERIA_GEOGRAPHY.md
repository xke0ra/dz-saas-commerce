# Algeria Geography

Last updated: 2026-04-28

This document defines how Algerian geography data is handled for checkout and shipping.

## Current Implementation

The application currently seeds:

- 58 active wilayas
- 1541 active communes

Seeder:

- `backend/database/seeders/AlgeriaGeographySeeder.php`

Seed data:

- `backend/database/seeders/data/algeria_wilayas_58.json`
- `backend/database/seeders/data/algeria_communes_1541.json`

The storefront API exposes active data through:

- `GET /api/storefront/geography/wilayas`
- `GET /api/storefront/geography/communes?wilaya_id=`

Checkout validates that the selected commune belongs to the selected wilaya in `App\Http\Requests\Storefront\QuickCheckoutRequest`.

## Source

The current local seed dataset is derived from `kossa/algerian-cities`, an MIT-licensed Laravel package that includes Algerian wilayas and communes in Arabic and French.

Source repository:

- https://github.com/kossa/algerian-cities

The local wilaya file is filtered to the 58-wilaya mapping currently used by checkout and shipping.

## 2026 Territorial Reform

As of April 2026, Algeria has a newer legal territorial organization:

- Law No. 26-06 of April 4, 2026
- Official Gazette No. 25 of April 5, 2026
- The law formalizes 69 wilayas while the commune count remains 1541.

References:

- https://news.radioalgerie.dz/en/node/83104
- https://legal-doctrine.com/ar/edition/reforme-territoriale-2026-ce-qui-change-pour-les-wilayas-et-les-communes-2dcf62d2ce16395411980a758f3617da

The implementation does not activate 69 wilayas yet because checkout and shipping require a complete, tested mapping of all 1541 communes to the new wilayas. Activating empty or partially mapped wilayas would create failed checkout and shipping scenarios.

## Upgrade Rule For 69 Wilayas

Before switching production data to 69 wilayas:

1. Add an authoritative 69-wilaya commune mapping dataset.
2. Update seed files and keep source attribution.
3. Add tests for new wilayas and reassigned communes.
4. Verify shipping rates can target the new wilaya IDs.
5. Add a migration or data command for existing merchants with rates on old wilaya IDs.
6. Update storefront copy and docs.
7. Update `PROJECT_DEEP_ANALYSIS_AND_AI_ROADMAP_AR.md`.

## Product Rule

Geography data is a checkout dependency. It must not be changed as a simple display list without validating:

- checkout validation
- shipping rate matching
- customer address storage
- order address storage
- shipment address storage
- existing merchant shipping configurations
