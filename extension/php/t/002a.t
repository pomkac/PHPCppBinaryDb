#!perl
use FindBin qw($Bin);
use File::Basename;

use lib "$Bin";
use Suite;

chdir($Bin);
check(basename($0));
# check_fail(basename($0));