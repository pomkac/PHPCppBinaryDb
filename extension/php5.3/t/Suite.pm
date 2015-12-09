package Suite;

use Exporter();
$VERSION = 1.00;
@ISA = qw(Exporter);

@EXPORT = qw/
  &check
/;

use strict;
use warnings;
use Test::More;
use Data::Dumper;
use File::Basename;

sub check {
	my $test_name = shift || die;
	plan tests => 2;
	
	my $fail = (-e "$test_name.err.eta");
	my $filter = $test_name=~/filter/;
	my $flag;
	my $path=dirname(__FILE__);
	$path=~s/^.+(\w\:[^\:]+)$/$1/;
	if ($filter) {
		$flag = system("php -n -d extension=".$path."/php_sbf.dll -f test_filter.php ${test_name}");
		ok(!$flag, "${test_name} - create sbf");
		return if $flag;		
		
		$flag = system("fc ${test_name}.eta ${test_name}.tst > ${test_name}.cmp");
		ok(!$flag, "${test_name} - compare with etalon");
		return if $flag;				
	} elsif ($fail) {
		$flag = system(qq!php -n -d extension=!.$path.qq!/php_sbf.dll -f test_php.php 005.t 2>&1 | perl -ne "print(\$1) if /(Exception.+with message (.+)) in/" > 005.t.err.tst!);
		ok(!$flag, "${test_name} - create sbf");
		return if $flag;		
		
		$flag = system("fc ${test_name}.err.eta ${test_name}.err.tst > ${test_name}.cmp");
		ok(!$flag, "${test_name} - compare with etalon");
		return if $flag;				
	} else {  
		$flag = system("php -n -d extension=".$path."/php_sbf.dll -f test_php.php ${test_name}");
		ok(!$flag, "${test_name} - create sbf");
		return if $flag;
		
		$flag = system("fc ${test_name}.eta ${test_name}.tst > ${test_name}.cmp");
		ok(!$flag, "${test_name} - compare with etalon");
		return if $flag;		
	}
	
#	unlink("${test_name}.tst");
#	unlink("${test_name}.err.tst");	
#	unlink("${test_name}.cmp");
#	unlink("${test_name}.dat");
#	unlink("${test_name}.eta");
}

1;

__END__

template for test.t: 

#!perl
use FindBin qw($Bin);
use File::Basename;

use lib "$Bin";
use Suite;

chdir($Bin);
check(basename($0));
