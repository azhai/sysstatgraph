sysstatgraph
============

Pretty web-facing semi-interactive graph of sysstat data; take a look at the website listed below for an active example.

WebSite
========

[http://magnetikonline.com/sysstatgraph/](http://magnetikonline.com/sysstatgraph/)

Metrics displayed
==================

The following system metrics from SYSSTAT reports are rendered:

* Tasks created (per second)
* Context switches (per second)
* CPU utilisation (User/System/IOwait)
* Memory usage / Swap usage (megabytes)
* Running/sleeping task count (threads)
* System load averages
* Network packets (received/transmitted per second) - per adapter
* Network kilobytes (received/transmitted per second) - per adapter

Updates
========

* 2011-04-07
Version 0.4 now allows multiple network adapters to be graphed, note the configuration file has changed slightly to support this. Also:
Cleanup of file line endings (all Linux LF ended now).
A few small re-factors, nothing visible with the UI.
Hopefully will have some time in the near future to add disk level statistics and test/fix to work with newer SYSSTAT releases.
