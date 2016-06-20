Multibyte Aware FPDF
====================
[FPDF](http://www.fpdf.org/) is a library for writing PDF documents in PHP.
FPDF only works correctly with [Windows-1252](https://en.wikipedia.org/wiki/Windows-1252)
encoded content and does not work properly if the
[mbstring function overload](http://php.net/manual/en/mbstring.overload.php)
option is enabled for PHP.

This adapter class allows FPDF to function in an environment with mbstring
function overloading is enabled. It still only supports Windows-1252 text,
encoding but no longer crashes or fails to run in an environemnt with
function overloading.
