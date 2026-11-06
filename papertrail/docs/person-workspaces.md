# One Login, Multiple Workspaces

Each person signs in using their main office User ID, existing office password,
and Head of Office login selection. In the top-right account menu, choose
**Switch Workspace** to select an assigned office or BAC role. Other users keep
their current login. Switching is not a new login and does not grant unassigned
roles.

| Main login | Additional workspace | Person |
| --- | --- | --- |
| MPDO-001 | BACCHAIR-001 | Edmar Peter T. Tambis |
| MEO-001 | BACVICE-001 | Roel J. Bano |
| MAO-001 | BACMEM-001 | Perpetua D. Salan |
| HRMO-001 | BACMEM-002 | Rogelio A. Layo |
| MSDWO-001 | BACMEM-003 | Jessica G. Resus |
| MCR-001 | BACSEC-002 | Meagan C. Matutes |

## Deployment

Back up the production database, deploy the code, then run:

```sh
php artisan migrate --force
php artisan optimize:clear
```

For a **new database only**, run the normal database seeder first, then:

```sh
php artisan db:seed --class=PersonWorkspaceSeeder
```

Existing deployments are linked by the data migration. It checks exact account
codes and roles, skips missing pairs, rejects conflicting links, and is
idempotent. Confirm all six pairs in Admin > Users > Edit > Assigned Workspaces.
Do not run the full database seeder on production just to link accounts.

## Identity and Documents

- Password, email verification, profile and signature setup use the main login.
- If the main login has no email or signature setup, linking transfers those
  details from the BAC profile. Existing main-login details take priority.
- Legacy BAC user rows are retained as role workspace profiles. Their independent
  passwords are replaced with random credentials and direct login/reset is blocked.
- Existing workspace IDs, document owners, routing and signed snapshots are not
  rewritten. Pending signatures stay in the corresponding workspace inbox.
- New signature audit records identify both the person and active workspace.
  Signing one role does not sign another role automatically.
- Disabling the main login blocks all its workspaces. Disabling an additional
  workspace blocks only that role, without deleting its records.
- Switching refreshes the session/CSRF token. Old forms in other tabs must be
  reloaded rather than silently submitting under a different role. Save drafts
  before switching workspaces.

## Additional People

An administrator can edit a main account and link another existing account after
confirming that both belong to the same person. Only active, unlinked non-admin
accounts can be assigned. Users cannot assign themselves roles. Disable a role
under Assigned Workspaces when access ends; do not delete its historical profile.
Do not assign a predecessor's personal login to a different employee.

## Rollback

The data migration deliberately does not restore retired passwords. Roll back
using the database backup and matching code together, not by deleting linked
user rows. Signed documents and audit records must remain intact.
