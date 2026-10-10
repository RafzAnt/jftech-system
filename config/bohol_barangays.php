<?php

// Local Bohol barangay suggestions for the service branches.
// Lists are kept in the project so registration works without an internet connection.
$lists = [
    'Albur' => 'Bahi|Basacdacu|Cantiguib|Dangay|East Poblacion|Ponong|San Agustin|Santa Filomena|Tagbuane|Toril|West Poblacion',
    'Antequera' => 'Angilan|Bantolinao|Bicahan|Bitaugan|Bungahan|Can-omay|Canlaas|Cansibuan|Celing|Danao|Danicop|Mag-aso|Poblacion|Quinapon-an|Santo Rosario|Tabuan|Tagubaas|Tupas|Ubojan|Viga|Villa Aurora',
    'Baclayon' => 'Buenaventura|Cambanac|Dasitam|Guiwanon|Landican|Laya|Libertad|Montana|Pamilacan|Payahan|Poblacion|San Isidro|San Roque|San Vicente|Santa Cruz|Taguihon|Tanday',
    'Balilihan' => 'Baucan Norte|Baucan Sur|Boctol|Boyog Norte|Boyog Proper|Boyog Sur|Cabudburan|Candasig|Cantalid|Cantomimbo|Cogon|Datag Norte|Datag Sur|Del Carmen Este|Del Carmen Norte|Del Carmen Sur|Del Carmen Weste|Del Rosario|Dorol|Haguilanan Grande|Hanopol Este|Hanopol Norte|Hanopol Weste|Magsija|Maslog|Sagasa|Sal-ing|San Isidro|San Roque|Santa Cruz|Santo Niño|Tagustusan',
    'Batuan' => 'Aloja|Behind The Clouds|Cabacnitan|Cambacay|Cantigdas|Garcia|Janlud|Poblacion Norte|Poblacion Sur|Poblacion Vieja|Quezon|Quirino|Rizal|Rosariohan|Santa Cruz',
    'Bilar' => 'Bonifacio|Bugang Norte|Bugang Sur|Cabacnitan|Cambigsi|Campagao|Cansumbol|Dagohoy|Owac|Poblacion|Quezon|Riverside|Rizal|Roxas|Subayon|Villa Aurora|Villa Suerte|Yanaya|Zamora',
    'Carmen' => 'Alegria|Bicao|Buenavista|Buenos Aires|Calatrava|El Progreso|El Salvador|Guadalupe|Katipunan|La Libertad|La Paz|La Salvacion|La Victoria|Matin-ao|Montehermoso|Montesuerte|Montesunting|Montevideo|Nueva Fuerza|Nueva Vida Este|Nueva Vida Norte|Nueva Vida Sur|Poblacion Norte|Poblacion Sur|Tambo-an|Vallehermoso|Villaflor|Villafuerte|Villarcayo',
    'Catigbian' => 'Alegria|Ambuan|Baang|Bagtic|Bongbong|Cambailan|Candumayao|Causwagan Norte|Hagbuaya|Haguilanan|Kang-iras|Libertad Sur|Liboron|Mahayag Norte|Mahayag Sur|Maitum|Mantasida|Poblacion|Poblacion Weste|Rizal|Sinakayanan|Triple Union',
    'Clarin' => 'Bacani|Bogtongbod|Bonbon|Bontud|Buacao|Buangan|Cabog|Caboy|Caluwasan|Candajec|Cantoyoc|Comaang|Danahao|Katipunan|Lajog|Mataub|Nahawan|Poblacion Centro|Poblacion Norte|Poblacion Sur|Tangaran|Tontunan|Tubod|Villaflor',
    'Cortes' => 'De la Paz|Fatima|Loreto|Lourdes|Malayo Norte|Malayo Sur|Monserrat|New Lourdes|Patrocinio|Poblacion|Rosario|Salvador|San Roque|Upper de la Paz',
    'Inabanga' => 'Anonang|Badiang|Baguhan|Bahan|Banahao|Baogo|Bugang|Cagawasan|Cagayan|Cambitoon|Canlinte|Cawayan|Cogon|Cuaming|Dagnawan|Dagohoy|Dait Sur|Datag|Fatima|Hambongan|Ilaud|Ilaya|Ilihan|Lapacan Norte|Lapacan Sur|Lawis|Liloan Norte|Liloan Sur|Lomboy|Lonoy Cainsican|Lonoy Roma|Lutao|Luyo|Mabuhay|Maria Rosario|Nabuad|Napo|Ondol|Poblacion|Riverside|Saa|San Isidro|San Jose|Santo Niño|Santo Rosario|Sua|Tambook|Tungod|U-og|Ubujan',
    'Loboc' => 'Agape|Alegria|Bagumbayan|Bahian|Bonbon Lower|Bonbon Upper|Buenavista|Bugho|Cabadiangan|Calunasan Norte|Calunasan Sur|Camayaan|Cambance|Candabong|Candasag|Canlasid|Gon-ob|Gotozon|Jimilian|Oy|Poblacion Ondol|Poblacion Sawang|Quinoguitan|Taytay|Tigbao|Ugpong|Valladolid|Villaflor',
    'Loon' => 'Agsoso|Badbad Occidental|Badbad Oriental|Bagacay Katipunan|Bagacay Kawayan|Bagacay Saong|Bahi|Basac|Basdacu|Basdio|Biasong|Bongco|Bugho|Cabacongan|Cabadug|Cabug|Calayugan Norte|Calayugan Sur|Cambaquiz|Campatud|Candaigan|Canhangdon Occidental|Canhangdon Oriental|Canigaan|Canmaag|Canmanoc|Cansuagwit|Cansubayon|Cantam-is Bago|Cantam-is Baslay|Cantaongon|Cantumocad|Catagbacan Handig|Catagbacan Norte|Catagbacan Sur|Cogon Norte|Cogon Sur|Cuasi|Genomoan|Lintuan|Looc|Mocpoc Norte|Mocpoc Sur|Moto Norte|Moto Sur|Nagtuang|Napo|Nueva Vida|Panangquilon|Pantudlan|Pig-ot|Pondol|Quinobcoban|Sondol|Song-on|Talisay|Tan-awan|Tangnan|Taytay|Ticugan|Tiwi|Tontonan|Tubodacu|Tubodio|Tubuan|Ubayon|Ubojan',
    'Maribojoc' => 'Agahay|Aliguay|Anislag|Bayacabac|Bood|Busao|Cabawan|Candavid|Dipatlong|Guiwanon|Jandig|Lagtangon|Lincod|Pagnitoan|Poblacion|Punsod|Punta Cruz|San Isidro|San Roque|San Vicente|Tinibgan|Toril',
    'Sagbayan' => 'Calangahan|Canmano|Canmaya Centro|Canmaya Diot|Dagnawan|Kabasacan|Kagawasan|Katipunan|Langtad|Libertad Norte|Libertad Sur|Mantalongon|Poblacion|Sagbayan Sur|San Agustin|San Antonio|San Isidro|San Ramon|San Roque|San Vicente Norte|San Vicente Sur|Santa Catalina|Santa Cruz|Ubojan',
    'Sevilla' => 'Bayawahan|Cabancalan|Calinga-an|Calinginan Norte|Calinginan Sur|Cambagui|Ewon|Guinob-an|Lagtangan|Licolico|Lobgob|Magsaysay|Poblacion',
    'Sikatuna' => 'Abucay Norte|Abucay Sur|Badiang|Bahaybahay|Cambuac Norte|Cambuac Sur|Canagong|Libjo|Poblacion I|Poblacion II',
    'Tagbilaran' => 'Bool|Booy|Cabawan|Cogon|Dampas|Dao|Manga|Mansasa|Poblacion I|Poblacion II|Poblacion III|San Isidro|Taloto|Tiptip|Ubujan',
    'Tubigon' => 'Bagongbanwa|Banlasan|Batasan|Bilangbilangan|Bosongon|Buenos Aires|Bunacan|Cabulihan|Cahayag|Cawayanan|Centro|Genonocan|Guiwanon|Ilihan Norte|Ilihan Sur|Libertad|Macaas|Matabao|Mocaboc Island|Panadtaran|Panaytayon|Pandan|Pangapasan|Pinayagan Norte|Pinayagan Sur|Pooc Occidental|Pooc Oriental|Potohan|Talenceras|Tan-awan|Tinangnan|Ubay Island|Ubojan|Villanueva',
];

return array_map(static fn (string $names): array => explode('|', $names), $lists);
