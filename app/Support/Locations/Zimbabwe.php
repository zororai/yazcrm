<?php

namespace App\Support\Locations;

// Zimbabwe's 10 provinces and their districts — the same list /screen uses
// for its Province → District filter.
final class Zimbabwe
{
    public const PROVINCE_DISTRICTS = [
        'Bulawayo'            => ['Bulawayo'],
        'Harare'              => ['Harare Urban', 'Harare Rural', 'Chitungwiza', 'Epworth'],
        'Manicaland'          => ['Buhera', 'Chimanimani', 'Chipinge', 'Makoni', 'Mutare', 'Mutasa', 'Nyanga'],
        'Mashonaland Central' => ['Bindura', 'Guruve', 'Mazowe', 'Mbire', 'Mount Darwin', 'Muzarabani', 'Rushinga', 'Shamva'],
        'Mashonaland East'    => ['Chikomba', 'Goromonzi', 'Hwedza', 'Marondera', 'Mudzi', 'Murewa', 'Mutoko', 'Seke', 'Uzumba-Maramba-Pfungwe'],
        'Mashonaland West'    => ['Chegutu', 'Hurungwe', 'Kariba', 'Makonde', 'Mhondoro-Ngezi', 'Sanyati', 'Zvimba'],
        'Masvingo'            => ['Bikita', 'Chiredzi', 'Chivi', 'Gutu', 'Masvingo', 'Mwenezi', 'Zaka'],
        'Matabeleland North'  => ['Binga', 'Bubi', 'Hwange', 'Lupane', 'Nkayi', 'Tsholotsho', 'Umguza'],
        'Matabeleland South'  => ['Beitbridge', 'Bulilima', 'Gwanda', 'Insiza', 'Mangwe', 'Matobo', 'Umzingwane'],
        'Midlands'            => ['Chirumanzu', 'Gokwe North', 'Gokwe South', 'Gweru', 'Kwekwe', 'Mberengwa', 'Shurugwi', 'Zvishavane'],
    ];
}
