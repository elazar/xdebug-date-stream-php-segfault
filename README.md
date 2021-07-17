The code in this repository causes a segmentation fault when run with PHP 8.0.6 on Fedora 33.

This issue has been [reported to the Xdebug project](https://bugs.xdebug.org/view.php?id=1995) for further investigation.

## Installation

1. Install dependencies.

    ```sh
    composer install
    ```

2. Run the script.

    ```sh
    php index.php
    ```

## Root Cause

Uncommenting the `closedir()` call near the end of `index.php` avoids the segmentation fault, so this presumably only happens on shutdown when the directory handle is left open, causing `StreamWrapper->dir_closedir()` to be implicitly invoked.

Uncommenting the `xdebug_start_trace()` call at the end of the script produces the trace in `xdebug.xt`. In particular, line 9 appears to indicate that the timezone is being corrupted in memory at some point.

```
0.0091     450976         -> Monolog\DateTimeImmutable->__construct($useMicroseconds = TRUE, $timezone = class DateTimeZone { public $time    zone_type = 3; public $timezone = 'À<83>çDJ^?' }) /home/matt/Code/Personal/sandbox/vendor/monolog/monolog/src/Monolog/Logger.php:309
```

Adding `var_dump($this->timezone);` to the bottom of `__construct()` in `vendor/monolog/monolog/src/Monolog/Logger.php` produces the expected output for my system.

```
vendor/monolog/monolog/src/Monolog/Logger.php:163:
class DateTimeZone#4 (2) {
  public $timezone_type =>
  int(3)
  public $timezone =>
  string(15) "America/Chicago"
}
```

Doing the same at the start of `addRecord()` (see line 6 in `xdebug.xt`) indicates that `$this->timezone` is corrupt at that point.

```
/home/matt/Code/Personal/sandbox/vendor/monolog/monolog/src/Monolog/Logger.php:292:
class DateTimeZone#4 (2) {
  public $timezone_type =>
  int(3)
  public $timezone =>
  string(6) "��g"
}
```

## GDB Backtrace

A segmentation fault occurs regardless of whether the conditions listed below are present. However, `gdb` has no available stack trace unless the conditions are met.

1. Xdebug is loaded
2. The `xdebug_start_trace()` at the bottom of `index.php` is uncommented.
3. `xdebug.mode` / `XDEBUG_MODE` is set to `trace`.

Without these conditions:

```
$ gdb php
GNU gdb (GDB) Fedora 10.2-1.fc33
Copyright (C) 2021 Free Software Foundation, Inc.
License GPLv3+: GNU GPL version 3 or later <http://gnu.org/licenses/gpl.html>
This is free software: you are free to change and redistribute it.
There is NO WARRANTY, to the extent permitted by law.
Type "show copying" and "show warranty" for details.
This GDB was configured as "x86_64-redhat-linux-gnu".
Type "show configuration" for configuration details.
For bug reporting instructions, please see:
<https://www.gnu.org/software/gdb/bugs/>.
Find the GDB manual and other documentation resources online at:
    <http://www.gnu.org/software/gdb/documentation/>.

For help, type "help".
Type "apropos word" to search for commands related to "word"...
Reading symbols from php...
Reading symbols from /usr/lib/debug/usr/bin/php-8.0.6-1.fc33.remi.x86_64.debug...
(gdb) run index.php
Starting program: /usr/bin/php index.php
[Thread debugging using libthread_db enabled]
Using host libthread_db library "/lib64/libthread_db.so.1".
[Inferior 1 (process 33184) exited with code 01]
Missing separate debuginfos, use: dnf debuginfo-install php-pecl-xdebug3-3.0.4-1.fc33.remi.8.0.x86_64
(gdb) bt
No stack.
```

With these conditions:

```
$ XDEBUG_MODE=trace gdb php
GNU gdb (GDB) Fedora 10.2-1.fc33
Copyright (C) 2021 Free Software Foundation, Inc.
License GPLv3+: GNU GPL version 3 or later <http://gnu.org/licenses/gpl.html>
This is free software: you are free to change and redistribute it.
There is NO WARRANTY, to the extent permitted by law.
Type "show copying" and "show warranty" for details.
This GDB was configured as "x86_64-redhat-linux-gnu".
Type "show configuration" for configuration details.
For bug reporting instructions, please see:
<https://www.gnu.org/software/gdb/bugs/>.
Find the GDB manual and other documentation resources online at:
    <http://www.gnu.org/software/gdb/documentation/>.

For help, type "help".
Type "apropos word" to search for commands related to "word"...
Reading symbols from php...
Reading symbols from /usr/lib/debug/usr/bin/php-8.0.6-1.fc33.remi.x86_64.debug...
(gdb) run index.php
Starting program: /usr/bin/php index.php
[Thread debugging using libthread_db enabled]
Using host libthread_db library "/lib64/libthread_db.so.1".

Program received signal SIGSEGV, Segmentation fault.
0x0000555555665dc7 in fetch_timezone_offset (tz=tz@entry=0x7ffff7278280, ts=ts@entry=1626524012,
    transition_time=transition_time@entry=0x7fffffffa6f0) at /usr/src/debug/php-8.0.6-1.fc33.remi.x86_64/ext/date/lib/parse_tz.c:1153
1153		if (ts < tz->trans[0]) {
Missing separate debuginfos, use: dnf debuginfo-install php-pecl-xdebug3-3.0.4-1.fc33.remi.8.0.x86_64
```

See `gdb.log` for a copy of the stack trace.
