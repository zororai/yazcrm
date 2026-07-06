<?php

namespace Database\Seeders;

use App\Models\ServiceProvider;
use Illuminate\Database\Seeder;

class BulawayoServiceDirectorySeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['organisation' => 'New Start centre', 'physical_address' => '1st & 2nd Floor Former Eye Clinic, 98 Samuel Parirenyatwa Road, Bulawayo', 'phone' => '+263 29 2882690', 'services_offered' => 'HIV testing, STI Testing and treatment/art initiation'],
            ['organisation' => 'Sexual rights centre', 'physical_address' => '11 Coghlan Road, Khumalo, Bulawayo.', 'phone' => '+263 777 645 392 or +263 772 386 102', 'services_offered' => 'Comprehensive Sexual and Reproductive Health and Rights (SRHR) services. HIV prevention, testing, and counseling. Condom distribution and access to other health commodities. Provision of care for survivors of sexual violence'],
            ['organisation' => 'Active Youth Zimbabwe', 'physical_address' => '5693 Drug Prevention & Rehabilitation Centre, Pumula South, Bulawayo', 'phone' => '+263 779 728 662', 'services_offered' => 'Address drug abuse in schools and workplaces. Awareness campaigns, peer education, and counseling services to combat substance abuse among youth in Zimbabwe.'],
            ['organisation' => "SOS Children's Villages Zimbabwe (Bulawayo)", 'physical_address' => '3 Lady Stanley Avenue Rowena', 'phone' => '+263 78 694260', 'services_offered' => 'Offers education (kindergartens/schools), vocational training, child-friendly spaces, and support for youth transitioning to independence.'],
            ['organisation' => 'Sethule Orphans Trust', 'physical_address' => 'Matopo District (south of Bulawayo), Zimbabwe', 'phone' => '(029) 2242383', 'services_offered' => 'Programmes that give children education, protection and identity, a foundation for life'],
            ['organisation' => 'ZVANDIRI', 'physical_address' => '11-12 Stoneridge Way North, Avondale, Harare, Zimbabwe', 'phone' => null, 'services_offered' => 'HIV Treatment Adherence & Support, Mental Health and Psychosocial Support, Sexual and Reproductive Health Services'],
            ['organisation' => 'PLAN International', 'physical_address' => 'CAW Offices, Cnr Waverly Rd/Walsal Rd, Thorngroove, Bulawayo', 'phone' => '+263 8677 000 201 (Ext 600)', 'services_offered' => 'Focuses on child rights, equality for girls, and quality basic education.'],
            ['organisation' => 'Caritas Zimbabwe', 'physical_address' => "St Mary's Cathedral Cnr 9th Ave/ Lobengula Street Bulawayo", 'phone' => '+263 292 60934 / +263 292 69218', 'services_offered' => 'Food security, water and sanitation and Disaster preparedness'],
            ['organisation' => 'Population Service Zimbabwe', 'physical_address' => '6967 Emganwini', 'phone' => '26377147828 / Econet: 08080019 or 08080020 / NetOne: 08010019 or 08010020 / WhatsApp: +263 772 145 222 / Direct line: +263 772 147 82', 'services_offered' => 'Family planning, Maternal Health: Ante-Natal Care (ANC) bookings and screenings'],
            ['organisation' => 'Human Impact Hub', 'physical_address' => '73 Heyman Road Bulawayo', 'phone' => '08677114443', 'services_offered' => 'Community Empowerment'],
            ['organisation' => 'Tree of LIFE', 'physical_address' => 'No physical address', 'phone' => 'WhatsApp: +263 77 233 4586', 'services_offered' => 'Trauma healing counselling, Psycho social support'],
            ['organisation' => 'BANTWANA Bulawayo', 'physical_address' => '34 Imbabala Way, Selbourne Park, Bulawayo', 'phone' => null, 'email' => 'admin@bantwana.co.zw', 'services_offered' => 'Health, Child Protection, Education, Economic Strengthening'],
            ['organisation' => 'Ingutsheni Mental Health Clinic', 'physical_address' => '23rd Ave, Bulawayo', 'phone' => '+263-292-466896; +263-292-466463-5; +263-292-463411-3', 'email' => 'information@ingutshenihospital.org.zw', 'services_offered' => 'Mental health support and treatment'],
            ['organisation' => 'Impilo Hospital', 'physical_address' => 'Verrah road MZILIKAZI SQUARE', 'phone' => '0778830466; +263 2212011-9, 2200374', 'email' => 'info@mpilo.org.zw', 'services_offered' => 'All clinical health services'],
            ['organisation' => 'Nkulumane Clinic', 'physical_address' => 'Nkulumane Drive, Bulawayo, Zimbabwe', 'phone' => '+263 29 2484152', 'services_offered' => 'Clinical health services'],
            ['organisation' => 'Tshabalala', 'physical_address' => 'Tshabalala', 'phone' => '480861', 'services_offered' => 'All clinical health services'],
            ['organisation' => 'Pelandaba', 'physical_address' => 'Pelandaba', 'phone' => '412461', 'services_offered' => 'All clinical health services'],
            ['organisation' => 'Magwegwe', 'physical_address' => 'Magwegwe', 'phone' => '427589', 'services_offered' => 'All clinical health services'],
            ['organisation' => 'Pumula', 'physical_address' => 'Pumula', 'phone' => '429080', 'services_offered' => 'All clinical health services'],
            ['organisation' => 'Luveve', 'physical_address' => 'Luveve', 'phone' => '520505/520200', 'services_offered' => 'All clinical health services'],
            ['organisation' => 'Njube', 'physical_address' => 'Njube', 'phone' => '419429', 'services_offered' => 'All clinical health services'],
            ['organisation' => 'E. F. Watson / Mpopoma', 'physical_address' => 'Mpopoma', 'phone' => '404214', 'services_offered' => 'All clinical health services'],
            ['organisation' => 'Mzilikazi', 'physical_address' => 'Mzilikazi', 'phone' => '202829', 'services_offered' => 'All clinical health services'],
            ['organisation' => 'P. M. R. (Princess Margaret Rose)', 'physical_address' => null, 'phone' => '60785', 'services_offered' => 'All clinical health services'],
            ['organisation' => 'Khami Road Clinic', 'physical_address' => 'Khami', 'phone' => '63207/61137', 'services_offered' => 'All clinical health services'],
            ['organisation' => 'Dr. Shennan Clinic', 'physical_address' => 'Suburbs', 'phone' => '479209', 'services_offered' => 'All clinical health services'],
            ['organisation' => 'Entumbane', 'physical_address' => 'Entumbane', 'phone' => '419809/416276', 'services_offered' => 'All clinical health services'],
            ['organisation' => 'Emakhandeni', 'physical_address' => 'Emakhandeni', 'phone' => '521677/8', 'services_offered' => 'All clinical health services'],
            ['organisation' => 'Nketa', 'physical_address' => 'Nketa', 'phone' => '462315', 'services_offered' => 'All clinical health services'],
            ['organisation' => 'Pumula South', 'physical_address' => '5693 Drug Prevention & Rehabilitation Centre, Pumula South, Bulawayo', 'phone' => '422395', 'services_offered' => 'All clinical health services'],
            ['organisation' => 'Lobengula', 'physical_address' => 'Not yet installed', 'phone' => 'Not yet installed', 'services_offered' => 'All clinical health services'],
            ['organisation' => 'Mahatshula', 'physical_address' => 'Not yet installed', 'phone' => 'Not yet installed', 'services_offered' => 'All clinical health services'],
            ['organisation' => 'Cowdray Park', 'physical_address' => 'Cowdry Park', 'phone' => '521306', 'services_offered' => 'All clinical health services'],
            ['organisation' => 'Northern Suburbs', 'physical_address' => 'Northend', 'phone' => '201960/203260', 'services_offered' => 'All clinical health services'],
            ['organisation' => 'ZNFPC Bulawayo', 'physical_address' => 'Lister House 88A Samuel Parirenyatwa Street 8th & 9th Avenue P.O. Box 1045. Bulawayo', 'phone' => null, 'services_offered' => 'Family planning, Services Ante-Natal Care (ANC) bookings and screenings'],
            ['organisation' => 'Nester Ambulence services', 'physical_address' => 'Josiah Tongogara RD', 'phone' => '0778681949', 'services_offered' => null, 'notes' => 'N/A'],
            ['organisation' => 'Premier Service Medical Aid Society', 'physical_address' => '40a Robert Mugabe Way', 'phone' => '09 619 578 ext. 62708', 'services_offered' => null, 'notes' => 'Closes at 16:30'],
            ['organisation' => 'Galen House Emergency Medical Clinic', 'physical_address' => 'Josiah Tongogara RD', 'phone' => '(029) 2881051', 'services_offered' => null, 'notes' => '24 hour service'],
            ['organisation' => 'Medical Air Rescue Services (MARS)', 'physical_address' => null, 'phone' => '(024) 2771221', 'services_offered' => null, 'notes' => '24 hour service'],
        ];

        foreach ($rows as $row) {
            ServiceProvider::firstOrCreate(
                ['organisation' => $row['organisation'], 'chapter' => 'Bulawayo'],
                [
                    'name'             => $row['organisation'],
                    'organisation'     => $row['organisation'],
                    'phone'            => $row['phone'] ?? null,
                    'email'            => $row['email'] ?? null,
                    'physical_address' => $row['physical_address'] ?? null,
                    'services_offered' => $row['services_offered'] ?? null,
                    'notes'            => $row['notes'] ?? null,
                    'chapter'          => 'Bulawayo',
                ]
            );
        }
    }
}
