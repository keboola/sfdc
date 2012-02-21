#/bin/bash

##
# Script saves database to sql files for GIT versioning. It looks for db login in application/configs/config.ini
# and it saves sql files to backup folder.
#
# @author Mirek Burkon <mirek@keboola.com>
# @author Jakub Matejka <jakub@keboola.com>
# @version 4.0
##
echo Keboola DB dumper v4.0

structure=1;
routines=0;
appdata=1;
userdata=1;
backup=0;

method=">";
fStructure=/sql/structure.sql
fApp=/sql/appData.sql
fUser=/sql/userData.sql
fRoutines=/sql/routines.sql

for i in $*; do
	case "$i" in
		-s)
			userdata=0;
			appdata=0;
			structure=1;
			routines=0;
			;;
		-r)
			userdata=0;
			appdata=0;
			structure=0;
			routines=1;
			;;
		-a)
			userdata=0;
			appdata=1;
			structure=0;
			routines=0;
			;;
		-u)
			userdata=1;
			appdata=0;
			structure=0;
			routines=0;
			;;
		-b)
			userdata=1;
			appdata=1;
			structure=1;
			routines=1;
			method=">>";
			backupID=`date +%F-%R:%S`;
			fstructure=/backup/db_$backupID.sql;
			fApp=$fStructure;
			fUser=$fStructure;
			fRoutines=$fStructure;
			;;
		*)
			echo Parameters:
			echo " -s - saves only db structure"
			echo " -a - saves only basic app data"
			echo " -u - saves only user data"
			echo " -r - saves only triggers and routines"
			echo " -b - saves all db to one file"
			exit 1
	esac
done


dir=`dirname $0`
base=`dirname $dir`

fConf="$dir/../application/configs/config.ini"


# look for config
if [ ! -f $fConf ]; then
	echo Config file $fConf not found.
	exit;
fi

# login
cfg=`grep -m 1 -E "^\s{0,}db.login" $fConf`
login=`echo "$cfg" | tr -d " " | awk -F"db.login=" {'print $2'} | tr -d [:cntrl:]`
if [ "$login" == "" ]; then
	echo Unable to read db login from config file $fConf.
	exit;
fi

# password
cfg=`grep -m 1 -E "^\s{0,}db.password" $fConf`
pass=`echo "$cfg" | tr -d " " | awk -F"db.password=" {'print $2'} | tr -d [:cntrl:]`
if [ "$pass" == "" ]; then
	echo Unable to read db password from config file $fConf.
	exit;
fi

# db
cfg=`grep -m 1 -E "^\s{0,}db.name" $fConf`
db=`echo "$cfg" | tr -d " " | awk -F"db.name=" {'print $2'} | tr -d [:cntrl:]`
if [ "$db" == "" ]; then
	echo Unable to read db name from config file $fConf.
	exit;
fi

# app data
cfg=`grep -m 1 -E "^\s{0,}db.appDataTables" $fConf`
appDataTables=`echo "$cfg" | awk -F"=" {'print $2'} | tr -d [:cntrl:] | tr -s " " | sed 's/^[ ]//g'`
if [ "$appDataTables" == "" -a "$appdata" == 1 ]; then
	echo Unable to read app data tables from config file $fConf.
	exit;
fi

# user data
cfg=`grep -m 1 -E "^\s{0,}db.userDataTables" $fConf`
userDataTables=`echo "$cfg" | awk -F"=" {'print $2'} | tr -d [:cntrl:] | tr -s " " | sed 's/^[ ]//g'`
if [ "$userDataTables" == "" -a "$userdata" == 1 ]; then
	echo Unable to read user data tables from config file $fConf.
	exit;
fi

if [ $structure == 1 ]; then
	echo "Dumping structure... > $base$fStructure"
	echo "mysqldump --no-data --skip-opt --disable-keys --default-character-set=utf8 --set-charset --force --skip-comments --add-drop-table --create-options --skip-triggers -u $login --password=$pass $db > $base$fStructure" | /bin/sh
	sed -e 's/\ AUTO_INCREMENT=[0-9]*//g' $base$fStructure > "$base$fStructure.tmp"
	mv "$base$fStructure.tmp" $base$fStructure
fi

if [ $appdata == 1 ]; then
	echo "Dumping application data... $method $base$fApp"
	echo "mysqldump --no-create-info --disable-keys --complete-insert --extended-insert --skip-comments --skip-opt --set-charset --force --default-character-set=utf8 --skip-triggers -u $login --password=$pass $db $appDataTables $method $base$fApp" | /bin/sh
	#echo "Dumping core ds nodes... >> $fApp"
	#echo "mysqldump --no-create-info --disable-keys --complete-insert --extended-insert --skip-comments --skip-opt --set-charset --force --default-character-set=utf8 --skip-triggers -w 'root=2' -u $login --password=$pass $db keboola_dsNodes >> $base$fApp" | /bin/sh
	sed -e s/INSERT\ INTO/REPLACE\ INTO/g $base$fApp > "$base$fApp.tmp"
	mv "$base$fApp.tmp" $base$fApp
fi

if [ $userdata == 1 ]; then
	echo "Dumping user data... $method $base$fUser"
	echo "mysqldump --no-create-info --disable-keys --complete-insert --extended-insert --skip-comments --skip-opt --set-charset --force --default-character-set=utf8 --skip-triggers -u $login --password=$pass $db $userDataTables $method $base$fUser" | /bin/sh
	sed -e s/INSERT\ INTO/REPLACE\ INTO/g $base$fUser > "$base$fUser.tmp"
	mv "$base$fUser.tmp" $base$fUser
fi

if [ $routines == 1 ]; then
	echo "Dumping routines... $method $base$fRoutines"
	echo "mysqldump --no-data --skip-opt --default-character-set=utf8 --set-charset --force --skip-comments --triggers --routines --no-create-info -u $login --password=$pass $db $method $base$fRoutines" | /bin/sh
fi

echo 'Done.'
