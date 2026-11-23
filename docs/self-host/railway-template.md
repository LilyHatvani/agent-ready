# Railway template: what "Deploy on Railway" needs

Status: **ready to build** (2026-11-23) · **Waits on:** a public image ([release.md](release.md)); the owner builds it
in Railway's template composer (no API makes one).

## Services

| Service | Source | Settings |
| --- | --- | --- |
| `MySQL` | Railway's MySQL | defaults, with its volume |
| `WordPress` | image `ghcr.io/lilyhatvani/agent-ready:latest` | a volume at `/var/www/html/wp-content/uploads`; **Generate domain** on port 80; 1 replica |

Only uploads live on the volume; core, plugins and themes come from the image on every deploy
([decisions/0004](../decisions/0004-what-lives-on-the-volume.md)).

## Variables on `WordPress`

| Variable | Template value | Why |
| --- | --- | --- |
| `WORDPRESS_DB_HOST` | `${{MySQL.MYSQLHOST}}:${{MySQL.MYSQLPORT}}` | the database, over the private network |
| `WORDPRESS_DB_NAME` | `${{MySQL.MYSQLDATABASE}}` | |
| `WORDPRESS_DB_USER` | `${{MySQL.MYSQLUSER}}` | |
| `WORDPRESS_DB_PASSWORD` | `${{MySQL.MYSQLPASSWORD}}` | by reference, so nothing is typed or copied |

**No salts to set.** The image hands WordPress its own placeholder, so WordPress makes its salts once and keeps them in
the database: sign-ins survive deploys. Setting `WORDPRESS_AUTH_KEY` and the other seven still overrides that.

Nothing else: the person deploying fills nothing in. They open the domain and finish WordPress's install (site name,
their admin account), then Users → Agents.

## Building it (the owner, once)

1. Railway: **New → Template**.
2. Add **MySQL** (Database → MySQL).
3. Add **Docker image** `ghcr.io/lilyhatvani/agent-ready:latest`, name it `WordPress`; add a volume at
   `/var/www/html/wp-content/uploads`; generate a domain on port 80.
4. Set the four variables above exactly as written.
5. Listing: name *Agent Ready*; short description *WordPress your AI can write for, with the reach you choose.*;
   category CMS. **Create**, then **Publish** if you want it in the marketplace. Copy its code for the README button.
6. Test from a fresh Railway account: deploy, wait for green, open the domain, install, create an agent, connect
   (M2's second line).

## The README button

```markdown
[![Deploy on Railway](https://railway.com/button.svg)](https://railway.com/deploy/<template code>)
```
