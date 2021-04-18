#!/usr/bin/perl

# Copyright 2017 Christian Woerstenfeld, git@loxberry.woerstenfeld.de
#
# Licensed under the Apache License, Version 2.0 (the "License");
# you may not use this file except in compliance with the License.
# You may obtain a copy of the License at
# 
#     http://www.apache.org/licenses/LICENSE-2.0
# 
# Unless required by applicable law or agreed to in writing, software
# distributed under the License is distributed on an "AS IS" BASIS,
# WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
# See the License for the specific language governing permissions and
# limitations under the License.


##########################################################################
# Modules
##########################################################################

use LoxBerry::System;
use LoxBerry::Web;
use LoxBerry::Log;
use CGI::Carp qw(fatalsToBrowser);
use CGI qw/:standard/;
use Config::Simple '-strict';
use HTML::Entities;
use Cwd 'abs_path';
use warnings;
use strict;
no  strict "refs"; 

##########################################################################
# Variables
##########################################################################
my $cgi						= CGI->new;
my $sml_device_list			="";
my @sml_devices				="";
my $logfile					="sml_emon.log"; 
my $maintemplatefilename	="settings.html";
my $helptemplatefilename	="help.html";
my $helpurl 				="https://www.loxwiki.eu/display/LOXBERRY/SML-eMon";

##########################################################################
# Read Settings
##########################################################################
# Version of this script
my $version	= LoxBerry::System::pluginversion();
my $plugin	= LoxBerry::System::plugindata();
my $log		= LoxBerry::Log->new ( name => 'SML-eMon', filename => $lbplogdir ."/". $logfile, append => 1 );
my $lang	= lblanguage();

LOGSTART "New admin call."      if $plugin->{PLUGINDB_LOGLEVEL};
$LoxBerry::System::DEBUG 	= 1 if $plugin->{PLUGINDB_LOGLEVEL} eq 7;
$LoxBerry::Web::DEBUG 		= 1 if $plugin->{PLUGINDB_LOGLEVEL} eq 7;
$log->loglevel($plugin->{PLUGINDB_LOGLEVEL});
$log->loglevel(7);
LOGWARN "Cannot read loglevel from Plugin Database" if ( $plugin->{PLUGINDB_LOGLEVEL} eq "" );
LOGDEB "Language is: " . $lang;

my $maintemplate = HTML::Template->new(
		filename => $lbptemplatedir . "/" . $maintemplatefilename,
		global_vars => 1,
		loop_context_vars => 1,
		die_on_bad_params=> 0,
		%htmltemplate_options,
		debug => 1
		);
my %L = LoxBerry::System::readlanguage($maintemplate);

##########################################################################
# Main program
##########################################################################

  &form;
  exit;

#####################################################
# Form-Sub
#####################################################

	sub form 
	{
		# Print Template header
		LoxBerry::Web::lbheader("SML-eMon", $helpurl, $helptemplatefilename);

		@sml_devices =split(/\n/,`ls  /dev/sml_lesekopf*`);
		foreach (@sml_devices)
		{
			my $device 	= $_;
			$device 		=~ s/([\n])//g;
			$device 		=~ s%/dev/sml_lesekopf_%%g;
			LOGINF "Device ready: $device";
			$sml_device_list .= '<a target="'.$device.'" href="http://'.$cgi->server_name().'/plugins/'.$lbpplugindir.'/?device='.$device.'">http://'.$cgi->server_name().'/plugins/'.$lbpplugindir.'/?device='.$device.'</a><br/>';
		}
		# Parse page
		$maintemplate->param( "LBPPLUGINDIR"	, $lbpplugindir);
		$maintemplate->param( "LOGO_ICON"		, get_plugin_icon(64) );
		$maintemplate->param( "VERSION"			, $version);
		$maintemplate->param( "sml_device_list"	, $sml_device_list);
		$maintemplate->param( "LOGLEVEL" 		, $L{"LOGGING.LOGLEVEL".$plugin->{PLUGINDB_LOGLEVEL}});
		$maintemplate->param( "LOGLEVEL" 		, "?" ) if ( $plugin->{PLUGINDB_LOGLEVEL} eq "" );
		# Start Workaround due to missing variable for Logview
		$lbplogdir								=~ s/$lbhomedir\/log\///; 
		# End Workaround due to missing variable for Logview
		$maintemplate->param( "LOGFILE" 		, $lbplogdir ."/". $logfile);

		print $maintemplate->output();
		
		# Parse page footer		
		LoxBerry::Web::lbfooter();
		LOGEND;
		exit;
	}
