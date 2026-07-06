<?php

namespace Database\Seeders;

use App\Models\ServiceProvider;
use Illuminate\Database\Seeder;

class ServiceProviderSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            // Manicaland Chapter
            ['name' => 'Shamiso Mangongo', 'organisation' => 'Mwana Trust', 'phone' => '0772602569', 'email' => 'director@mwanatrust.org.zw', 'physical_address' => 'ZIMTA House, No. 6 Aerodrome Road, Mutare', 'chapter' => 'Manicaland'],
            ['name' => 'Tariro Guwira', 'organisation' => 'National Association of Freelance Journalists (NASJ)', 'phone' => '0782996147', 'email' => 'guwirapre@gmail.com', 'physical_address' => 'Manica Post, 87 Herbert Chitepo Street, Mutare', 'chapter' => 'Manicaland'],
            ['name' => 'Rumbidzai Munhanzi', 'organisation' => 'Tariro', 'phone' => '0773440960', 'email' => 'Rmunhanzi@yahoo.com', 'physical_address' => 'Stand number 257-9 5th St Mutare', 'chapter' => 'Manicaland'],
            ['name' => 'Mufaro Mashanda', 'organisation' => 'Zimbabwe National Network of People Living with HIV and AIDS (ZNNP+)', 'phone' => '0772968040', 'email' => 'mashandamufaro@gmail.com', 'physical_address' => 'TelOne Building Cnr 1st Ave and 3rd St Mutare', 'chapter' => 'Manicaland'],
            ['name' => 'Casper Pound', 'organisation' => 'Family AIDS Support Organization (FASO)', 'phone' => '0775089361', 'email' => 'fasomutare@gmail.com', 'physical_address' => '12 Runde Cresent, Yeoville, Mutare', 'chapter' => 'Manicaland'],
            ['name' => 'Blessing Mutama', 'organisation' => 'Farm Orphan Support Trust (FOST)', 'phone' => '0772819615', 'email' => 'Blessing@fost.co.zw', 'physical_address' => 'c/o Agricultural House, 1 Adylinn Road, Marlborough, Harare', 'chapter' => 'Manicaland'],
            ['name' => 'Calvin Mapangisana', 'organisation' => 'Gays and Lesbians Association of Zimbabwe (GALZ)', 'phone' => '0775048494', 'email' => 'Calvin@galz.co.zw', 'physical_address' => '141 Hebert Chitepo St Mutare', 'chapter' => 'Manicaland'],
            ['name' => 'Grace Chanakira', 'organisation' => 'Department of Social Development (DSD)', 'phone' => '0782558310', 'email' => 'gracechanakirah@gmail.com', 'physical_address' => 'Local Government Complex, Office 2A. Mutare', 'chapter' => 'Manicaland'],
            ['name' => 'Langton Makoni', 'organisation' => 'National Association of Social Workers (NASW)', 'phone' => '0772351138', 'email' => 'langtonlm@yahoo.com', 'physical_address' => '672 Area 13, Dangamvura, Mutare', 'chapter' => 'Manicaland'],
            ['name' => 'Ropafadzo Mukwazvure', 'organisation' => 'QUAPAZ', 'phone' => '0774590534', 'email' => 'rmukwazvure@gmail.com', 'physical_address' => 'Old MS Building Birchenough Bridge', 'chapter' => 'Manicaland'],
            ['name' => 'LIoyd Chirombo', 'organisation' => 'Neshasha Trust', 'phone' => '0712111458', 'email' => 'LIoydchirombo92@gmail.com', 'physical_address' => '495, 275 Tait Avenue, Rusape', 'chapter' => 'Manicaland'],
            ['name' => 'Mupfumwa Wallace', 'organisation' => 'Freedom for Disabled Persons in Zimbabwe', 'phone' => '0777055098', 'email' => 'mupfumwa@gmail.com', 'physical_address' => 'Moffat Offices, Sakubva, Mutare', 'chapter' => 'Manicaland'],
            ['name' => 'Magumise Evans', 'organisation' => 'Simukai Child Protection Programme', 'phone' => '0773887617', 'email' => 'evans@simukaicpp.org', 'physical_address' => 'No 9 Aerodrome Road, Mutare', 'chapter' => 'Manicaland'],
            ['name' => 'Matilda Mwera', 'organisation' => 'Hope for Kids Zimbabwe', 'phone' => '0772551432', 'email' => 'maltildamwera@gmail.com', 'physical_address' => '9284 Pegasus Dangamvura Mutare', 'chapter' => 'Manicaland'],
            ['name' => 'Janet Matahwa', 'organisation' => 'Centre for Sexual Health and HIV/AIDS Research Zimbabwe (CeSHHAR)', 'phone' => '0714064221', 'email' => 'matahwajanet@gmail.com', 'physical_address' => '3267 Park Road, Muneni Industrial Site, Sakubva, Mutare', 'chapter' => 'Manicaland'],

            // Masvingo Chapter
            ['name' => 'Madamombe Tapiwa', 'organisation' => 'Marget Hugo School of the Blind (High School)', 'phone' => '0772956834', 'email' => 'm.hugohigh@gmail.com', 'physical_address' => 'Copota Mission, Ward 6, Chief Zimuto Area, Masvingo', 'chapter' => 'Masvingo'],
            ['name' => 'Madzokere Clever', 'organisation' => 'Marget Hugo School of the Blind (Primary School)', 'phone' => '0773648444', 'email' => 'copota@gmail.com', 'physical_address' => 'Copota Mission, Ward 6, Chief Zimuto Area, Masvingo', 'chapter' => 'Masvingo'],
            ['name' => 'Joseph Makuni', 'organisation' => 'Legal Resources Foundation', 'phone' => '0774863306', 'email' => 'Masvingolaw@lrf.co.zw', 'physical_address' => '1st Floor, Stand No. 326 & 327, ZIMRE Centre, Corner Hughes St./S. Mazorodze St. Masvingo', 'chapter' => 'Masvingo'],
            ['name' => 'Kudzai Dakwa', 'organisation' => 'Masvingo Association of Residential Care Facilities Trust', 'phone' => '0775133194', 'email' => 'dakwakudzai@gail.com', 'physical_address' => '1002 Mutirikwi Street, Eastvale, Masvingo (housed at Alpha Cottages)', 'chapter' => 'Masvingo'],
            ['name' => 'Ivy Gavumende', 'organisation' => 'Building Bridges Zimbabwe Trust (BBZT)', 'phone' => '0772314242', 'email' => 'igavumende@gmail.com', 'physical_address' => 'C/O Mucheke Hall Mucheke A Masvingo', 'chapter' => 'Masvingo'],
            ['name' => 'Tawanda Mafuta', 'organisation' => 'Zimbabwe Council of Churches (ZCC) Masvingo Office', 'phone' => '0775526002', 'email' => 'Mafutatawanda40.tm@gmail.com', 'physical_address' => 'Lutheran Church, Kirton Street, Masvingo', 'chapter' => 'Masvingo'],

            // Matabeleland Chapter
            ['name' => 'Moyo Phakamani', 'organisation' => 'Department of Social Development', 'phone' => '0776423247', 'email' => 'Moyophakamani1978@gmail.com', 'physical_address' => 'Mhlanhlandlela Government Complex, Ministry of Public Service, Labour and Social Development, Bulawayo', 'chapter' => 'Matabeleland'],
            ['name' => 'Thobekile Sithole', 'organisation' => 'Justice for Children Trust', 'phone' => '0773669888', 'email' => 'lawyer4@jctrust.co.zw', 'physical_address' => '2nd Floor, Mership House, Joshua Nkomo Street/9th Avenue, Bulawayo', 'chapter' => 'Matabeleland'],
            ['name' => 'Nollet Ncube', 'organisation' => 'Lighthouse Children\'s Trust', 'phone' => '0785027955', 'email' => 'nolletncube@gmail.com', 'physical_address' => '27 Herbert Chitepo/1st Ave, Bulawayo', 'chapter' => 'Matabeleland'],
            ['name' => 'Sithembinkosi Moyo', 'organisation' => 'Nehemiah Project', 'phone' => '0772873903', 'email' => 'emmynare@gmail.com', 'physical_address' => '60 Jason Moyo Street, corner 5th Avenue, Bulawayo', 'chapter' => 'Matabeleland'],
            ['name' => 'Talent Gutu', 'organisation' => 'Contact Counselling Centre', 'phone' => '07795380776', 'email' => 'contact@contactfcc.co.zw', 'physical_address' => '9 Barbour Avenue, Parkview, Bulawayo', 'chapter' => 'Matabeleland'],
            ['name' => 'Patience Dube', 'organisation' => 'Hope Alive Children\'s Network', 'phone' => '0719699825', 'email' => 'hopealivechildnetwork@gmail.com', 'physical_address' => 'Plot 64 Tsholotsho Road, Lupane', 'chapter' => 'Matabeleland'],
            ['name' => 'Samkele Gondo', 'organisation' => 'Sinampande Women\'s Trust', 'phone' => '0787889682', 'email' => 'gondosamkele@gmail.com', 'physical_address' => 'Sinampande Secondary School, Chief Sinampande, Mpande Village, Binga', 'chapter' => 'Matabeleland'],
            ['name' => 'Dube Arnold', 'organisation' => 'Yes Trust Zimbabwe', 'phone' => '0714355280', 'email' => 'Arnold1993dube@gmail.com', 'physical_address' => '616 Medium Density Plumtree', 'chapter' => 'Matabeleland'],
            ['name' => 'Justice Zvaita', 'organisation' => 'Zimbabwe Climate Change Coalition (ZCCC)', 'phone' => '0716250233', 'email' => 'Zcc.coalition@gmail.com', 'physical_address' => 'Office 12, Empire House, Corner 6th Avenue and Joshua Nkomo Moyo Street, Bulawayo', 'chapter' => 'Matabeleland'],
            ['name' => 'Skholiwe Ncube', 'organisation' => 'Scripture Union/Thuthuka', 'phone' => '0772872444', 'email' => 'skholiwe@gmail.com', 'physical_address' => '125 Robert Mugabe Way, 13th Avenue, Bulawayo', 'chapter' => 'Matabeleland'],
            ['name' => 'Gibson Chitiga', 'organisation' => 'Plan International Bulawayo Office', 'phone' => '0772290579', 'email' => 'Gibson.chitiga@plan-interational.org', 'physical_address' => 'Corner Waverly and Walsall Street, Thorngrove, Bulawayo', 'chapter' => 'Matabeleland'],
            ['name' => 'Benjamin Sande', 'organisation' => 'Positive Living Zimbabwe', 'phone' => '0777177026', 'email' => 'positivelivingzim@gmail.com', 'physical_address' => '705 Charter House, Leopold Takawira Street, Bulawayo', 'chapter' => 'Matabeleland'],
            ['name' => 'Jabulani Tshabalala', 'organisation' => 'Umguza AIDS Foundation', 'phone' => '0775823654', 'email' => null, 'physical_address' => '986 Habane Township, Esigodhini, Matabeleland South', 'chapter' => 'Matabeleland'],

            // Midlands Chapter
            ['name' => 'Chindodzi E', 'organisation' => 'WORPHAN', 'phone' => '0772358569', 'email' => 'echindonzi@gmail.com', 'physical_address' => '30 Lalbagh Ave, Ridgemont, Gweru', 'chapter' => 'Midlands'],
            ['name' => 'Veronica Nhemachena', 'organisation' => 'MASO', 'phone' => '0773725428', 'email' => 'veronhemachena@gmail.com', 'physical_address' => 'Opposite Mkoba Teacher\'s College, Chilimanzi Road, Mkoba 8, Gweru', 'chapter' => 'Midlands'],
            ['name' => 'Saria Kanyani', 'organisation' => 'Childline', 'phone' => '0771019253', 'email' => 'Saria.kanyani1970@gmail.com', 'physical_address' => 'No. 11 Lobengula Avenue, Gweru', 'chapter' => 'Midlands'],
            ['name' => 'Batirai Chinyoka', 'organisation' => 'St Daniels Children Centre', 'phone' => '0715926500', 'email' => 'Batiechinyok8@gmail.com', 'physical_address' => 'Betseranai CBC, P. Bag 315, Mataga Mberengwa', 'chapter' => 'Midlands'],
            ['name' => 'Blessing Benjamin', 'organisation' => 'Queen of Peace Rehabilitation Centre', 'phone' => '0773409121', 'email' => 'Blessy2000@gmail.com', 'physical_address' => 'Stand No: 1588, Zvishavane', 'chapter' => 'Midlands'],
            ['name' => 'Mafukidze Brenda', 'organisation' => 'Midlands Children Hope Foundation', 'phone' => '0782082788', 'email' => 'bmmofu@gmail.com', 'physical_address' => '87 Lobengula Avenue, Gweru', 'chapter' => 'Midlands'],
            ['name' => 'Tatenda Furamera', 'organisation' => 'Jointed Hands Welfare Organization (JHWO)', 'phone' => '0776451513', 'email' => 'Furameratatenda2@gmail.com', 'physical_address' => '990 Shefield Rd, Light Industrial Site, Gweru', 'chapter' => 'Midlands'],
            ['name' => 'Muzokura Kenneth', 'organisation' => 'Tungamirai organization', 'phone' => '0777697300', 'email' => 'Muzokuake@gmail.com', 'physical_address' => '345 Mkoba 6, Mkoba, Gweru', 'chapter' => 'Midlands'],
            ['name' => 'Chikoto A', 'organisation' => 'Social Welfare', 'phone' => '0712555397', 'email' => 'archiechikoto@yahoo.co.uk', 'physical_address' => 'New Government Complex, Department of Social Services, Office 909-916, Gweru', 'chapter' => 'Midlands'],

            // Mashonaland Chapter
            ['name' => 'Fungai Dewere', 'organisation' => 'Terre des Hommes (TDH) – Germany', 'phone' => '0771332862', 'email' => 'fungai.dewere@tdh-southernafrica.org', 'physical_address' => '155 Borrowdale Road, Borrowdale, Harare', 'chapter' => 'Mashonaland'],
            ['name' => 'Mlungisi Nyathi', 'organisation' => 'Terre des Hommes (TDH) - Swiss', 'phone' => '0773739619', 'email' => 'mlungisi.nyathi@terredeshommes.ch', 'physical_address' => '155 Borrowdale Road, Borrowdale, Harare', 'chapter' => 'Mashonaland'],
            ['name' => 'Getrude Ndlovu', 'organisation' => 'Plan International – Harare office', 'phone' => '0713832299', 'email' => 'getrude.ndlovu@planinternational.org', 'physical_address' => '7 Lezard Avenue, Milton Park, Harare', 'chapter' => 'Mashonaland'],
            ['name' => 'Pamela Musimwa', 'organisation' => 'Justice for Children Trust', 'phone' => '0777000542', 'email' => 'Lawyer3@jctrust.co.zw', 'physical_address' => '66 Blakeway Drive, Belverdere, Harare', 'chapter' => 'Mashonaland'],
            ['name' => 'Dr Lamiel BK Phiri', 'organisation' => 'Tusanani Cover Trust', 'phone' => '0772394631', 'email' => 'lamielbkp@gmail.com', 'physical_address' => 'No 4, Derry Road, Waterfalls, Harare', 'chapter' => 'Mashonaland'],
            ['name' => 'Madrine Chiku', 'organisation' => 'Research and Advocacy Unit', 'phone' => '0772560064', 'email' => 'madrinechiku@rau.co.zw', 'physical_address' => '7A Sudbury Avenue, Monavale, Harare', 'chapter' => 'Mashonaland'],
            ['name' => 'Shamiso Moyo', 'organisation' => 'Farm Orphan Support Trust (FOST)', 'phone' => '0778528441', 'email' => 'shamiso@fost.co.zw', 'physical_address' => 'Agricultural House, 1 Adylinn Road, Marlborough, Harare', 'chapter' => 'Mashonaland'],
            ['name' => 'Nyasha Kurebwa', 'organisation' => 'World Vision Zimbabwe', 'phone' => '0773980070', 'email' => 'nyasha_kurebwa@wvi.org', 'physical_address' => '59 Joseph Road, Off Nursery Road, Mount Pleasant, Harare', 'chapter' => 'Mashonaland'],
            ['name' => 'Kellivn Nyamudeza', 'organisation' => 'SOS Children\'s Villages', 'phone' => '0773403797', 'email' => 'Kellivn.Nyamudeza@sos-kd.org', 'physical_address' => 'Mon Repos Building, Newlands Bypass, Newlands, Harare', 'chapter' => 'Mashonaland'],
            ['name' => 'Felicia Mangwende', 'organisation' => 'Regional Network for Children and Young Peoples Trust (RNCYPT)', 'phone' => '0718340650', 'email' => 'feliciamangwende@rncypt.org', 'physical_address' => 'No. 3 Ifield Road, Marlbereign, Harare', 'chapter' => 'Mashonaland'],
        ];

        foreach ($rows as $row) {
            ServiceProvider::firstOrCreate(
                ['name' => $row['name'], 'organisation' => $row['organisation']],
                $row
            );
        }
    }
}
