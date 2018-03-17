
Additional infos:

If you want to use one service check to monitor the quota of all users or groups, you might need to update the RRD/XML file of the perfdata with rrd_convert.pl by PNP4NAGIOS (or use cacti ;-)).

perl rrd_convert.pl --check_command=check_quota --cfg_dir=/path/to/pnp4nagios/etc --dry-run

And, if you like the resulting output, do it without the --dry-run. The check_command.cfg and XML-definition for the service using check_quota will then be updated for multiple rrds per check; the needed number of RRDs will be created during this process as well. Any new perfdata entries will be added automatically as well.

This does cost much more diskspace than the usual non-MULTIPLE RRD format, but is needed if you want nice graphs for PNP4NAGIOS.

