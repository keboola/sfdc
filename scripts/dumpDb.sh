#/bin/bash

##
# dumpne verzovan√© ńć√°sti datab√°ze do sql ouborŇĮ
# pouŇĺ√≠v√° root login a heslo z ./maintenance.ini
# jm√©no datab√°ze si najde v ./maintenance.ini
# jm√©na tabulek tak√© hled√° v ./maintenance.ini
# sql soubory sype do ../sql/ z√°lohy db se dńõlaj√≠ do ../backup/db_%date%
#
# @author Mirek BurkoŇą <mirek@keboola.com>
# verze 3.2
##
echo Keboola DB dumper v3.2

struktura=1;
routines=0;
appdata=1;
userdata=1;
backup=0;

method=">";
fStruktura=/sql/struktura.sql
fApp=/sql/appData.sql
fUser=/sql/userData.sql
fRoutines=/sql/routines.sql

for i in $*; do
	case "$i" in
		struktura)
			userdata=0;
			appdata=0;
			struktura=1;
			routines=0;
			;;
		routines)
			userdata=0;
			appdata=0;
			struktura=0;
			routines=1;
			;;
		appdata)
			userdata=0;
			appdata=1;
			struktura=0;
			routines=0;
			;;
		userdata)
			userdata=1;
			appdata=0;
			struktura=0;
			routines=0;
			;;
		backup)
			userdata=1;
			appdata=1;
			struktura=1;
			routines=1;
			method=">>";
			backupID=`date +%F-%R:%S`;
			fStruktura=/backup/db_$backupID.sql;
			fApp=$fStruktura;
			fUser=$fStruktura;
			fRoutines=$fStruktura;
			;;
		*)
			echo Parametry:
			echo "  struktura  dumpne se pouze db struktura"
			echo "  appdata    dumpnou se pouze z√°kladn√≠ data aplikace"
			echo "  userdata   dumpnou se pouze uŇĺivatelsk√° data"
			echo "  routines   dumpnou se pouze triggery a rutiny"
			echo "  backup     dumpne se cel√° datab√°ze vńćetnńõ dat a triggerŇĮ a rutin do jednoho souboru"
			exit 1
	esac
done


dir=`dirname $0`
base=`dirname $dir`

fConf="$dir/../application/configs/config.ini"


# nańć√≠t√°me konfiguraci
if [ ! -f $fConf ]; then
	echo Nebyla nalezena konfigurace $fConf
	exit;
fi

# login
cfg=`grep -m 1 -E "^\s{0,}db.login" $fConf`
login=`echo "$cfg" | tr -d " " | awk -F"db.login=" {'print $2'} | tr -d [:cntrl:]`
if [ "$login" == "" ]; then
	echo NepodaŇôilo se zjistit login pro pŇô√≠stupo do databz√°ze z $fConf
	exit;
fi

# password
cfg=`grep -m 1 -E "^\s{0,}db.password" $fConf`
pass=`echo "$cfg" | tr -d " " | awk -F"db.password=" {'print $2'} | tr -d [:cntrl:]`
if [ "$pass" == "" ]; then
	echo NepodaŇôilo se zjistit heslo pro pŇô√≠stupo do databz√°ze z $fConf
	exit;
fi

# db
cfg=`grep -m 1 -E "^\s{0,}db.db" $fConf`
db=`echo "$cfg" | tr -d " " | awk -F"db.db=" {'print $2'} | tr -d [:cntrl:]`
if [ "$db" == "" ]; then
	echo NepodaŇôilo se zjistit jm√©no databz√°ze z $fConf
	exit;
fi

# app data
cfg=`grep -m 1 -E "^\s{0,}db.appDataTables" $fConf`
appDataTables=`echo "$cfg" | awk -F"=" {'print $2'} | tr -d [:cntrl:] | tr -s " " | sed 's/^[ ]//g'`
if [ "$appDataTables" == "" -a "$appdata" == 1 ]; then
	echo Nebyla nalezena definice aplikańćn√≠ch dat, pokrańćujeme.
fi

# user data
cfg=`grep -m 1 -E "^\s{0,}db.userDataTables" $fConf`
userDataTables=`echo "$cfg" | awk -F"=" {'print $2'} | tr -d [:cntrl:] | tr -s " " | sed 's/^[ ]//g'`
if [ "$userDataTables" == "" -a "$userdata" == 1 ]; then
	echo Nebyla nalezena definice uŇĺivatelsk√Ĺch dat v $fConf
	exit;
fi

echo
echo "DB: $db"
echo "Login: $login"
echo "Password: ****"
echo "Aplikańćn√≠ data: $appDataTables"
echo "UŇĺivatelsk√° data: $userDataTables"
echo


if [ $struktura == 1 ]; then
	echo "Dumping structure... > $base$fStruktura"
	echo "mysqldump --no-data --skip-opt --disable-keys --default-character-set=utf8 --set-charset --force --skip-comments --add-drop-table --create-options --skip-triggers -u $login --password=$pass $db > $base$fStruktura" | /bin/sh
	sed -e 's/\ AUTO_INCREMENT=[0-9]*//g' $base$fStruktura > "$base$fStruktura.tmp"
	mv "$base$fStruktura.tmp" $base$fStruktura
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
