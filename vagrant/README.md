Opera DNS UI Vagrant
====================

Vagrant and ansible configuration to get a local development or test environment
up and running quickly and efficiently.

Requirements
------------
* Vagrant 2+

Usage
-----
1. Check out this repository
2. Enter the 'vagrant' directory
3. Run `vagrant up`
4. By default vagrant will map the webserver to port 8000, and the DNS server to
   port 5300, but it will pick another port when it is in use. Look for:
    ```
     ==> default: Forwarding ports...
      default: 80 (guest) => 8000 (host) (adapter 1)
      default: 53 (guest) => 5300 (host) (adapter 1)
    ```
5. Browse to http://localhost:8000 (or the port found in step 4)
6. Dig using `dig <domain> @127.0.0.1 -p 5300` (or the port found in step 4)

Updating
--------
If you made changes to the ansible scripts or settings and wish to roll out
again, do the following:
1. Remove your config/config.ini DNS UI configuration (ansible will recreate it)
2. Run `vagrant provision`

If you made a lot of impacting changes which might interfere with the existing
vagrant box, just toss & recreate it:
```
vagrant destroy -f
vagrant up
```
