# aim
Web service displays RSSI readings from a Mikrotik wifi device in convenient mobile dashboard. Intended for use on handheld device while aiming a dish on a tower.

# Screenshot

<img src="screenshot.png?raw=true" width="400">

# Demo

https://aim.hamwan.net

# Requirements

On the server:

- A web server with HTTPS (nginx or Apache)
- PHP with the SNMP extension
- net-snmp command-line tools (optional, useful for testing)

Debian/Ubuntu:

```
# Apache with php-fpm (Debian's default with Apache's mpm_event)
sudo apt install php-fpm php-snmp snmp
sudo a2enmod proxy_fcgi setenvif
sudo a2enconf php*-fpm
sudo systemctl restart apache2

# or Apache with mod_php (PHP runs inside Apache; needs mpm_prefork)
sudo apt install php libapache2-mod-php php-snmp snmp

# or nginx (always uses php-fpm)
sudo apt install php-fpm php-snmp snmp
```

To see which one Apache is using: `apache2ctl -M | grep -E 'php|proxy_fcgi'`.

nginx only: add this to the site's `server` block, then reload nginx:

```
index index.html;
location ~ \.php$ {
    include snippets/fastcgi-php.conf;
    fastcgi_pass unix:/run/php/php-fpm.sock;   # see ls /run/php/ for the actual name
}
```

Redirect plain http to https too, or browsers will show the page as "Not Secure".

On each radio:

- SNMP enabled, with a community the server can read (default `hamwan`) and the server's address allowed in `/snmp community`.

The browser loads jQuery and Flot from public CDNs, so the phone needs internet access.

# Configuration

The SNMP community defaults to `hamwan`. To change it, set `AIM_COMMUNITY` for PHP:

- php-fpm (with Apache or nginx) clears environment variables, so set it in the pool config (e.g. `/etc/php/*/fpm/pool.d/www.conf`): `env[AIM_COMMUNITY] = yourcommunity`, then restart php-fpm.
- Apache with mod_php: `SetEnv AIM_COMMUNITY yourcommunity` in the site's config.

# Troubleshooting

Open `rssi.php?host=<radio>&debug=1` in a browser. It returns JSON with the reading, or an `error` explaining what failed, plus every SNMP request it tried.

Test SNMP from the server:

```
snmpwalk -v1 -c hamwan <radio> 1.3.6.1.4.1.14988.1.1.1.1.1.4
```

# Station vs. AP mode

`rssi.php` first reads the station signal (`mtxrWlStatStrength`), which is what a client radio hears from its AP. That is the intended use.

If that is empty, as on a radio in AP mode, it falls back to the AP's registration table (`mtxrWlRtabStrength`):

- **No clients connected:** returns an error.
- **One client:** reports the signal the AP hears from that client.
- **Several clients:** reports whichever client is strongest at that moment, chosen again on every reading. The reading can jump between clients, and you may not be watching the one you are aiming. The `oid` field in the JSON ends in the chosen client's MAC address (as decimal octets).

These OIDs come from the legacy `wireless` package. RouterOS v7 `wifi`/wifiwave2 interfaces may not populate them; check with the `snmpwalk` above.
