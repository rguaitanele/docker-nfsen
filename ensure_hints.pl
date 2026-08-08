#!/usr/bin/perl
use strict;
use warnings;

use lib '/usr/local/nfsen/libexec';
use NfConf;
use NfSen;

NfConf::LoadConfig() or die "Não foi possível carregar nfsen.conf\n";
my $hints = NfSen::LoadHints();

$$$hints{'version'} = $ENV{'NFSEN_VERSION'} // '1.3.11';
$$$hints{'nfdump'} = $ENV{'NFDUMP_MAJOR'} // 7;
$$$hints{'subdirlayout'} = $NfConf::SUBDIRLAYOUT;
$$$hints{'installed'} ||= time();
delete $$$hints{'sources'};
for my $source (keys %NfConf::sources) {
    $$$hints{'sources'}{$source} = $NfConf::sources{$source}{'port'};
}

my $error = NfSen::StoreHints();
die "Não foi possível atualizar hints: $error\n" if $error;
print "Metadados do NfSen atualizados.\n";
