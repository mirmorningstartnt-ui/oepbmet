<?php
/**
 * Destination country name => ISO-3166-1 alpha-2 code.
 * The alpha-2 code is the first segment of the Emigration Clearance number
 * (e.g. MD = Moldova  ->  MD-I-2026-09890023).
 */
function ec_countries() {
    return [
        'Algeria' => 'DZ', 'Angola' => 'AO', 'Argentina' => 'AR', 'Armenia' => 'AM', 'Australia' => 'AU',
        'Austria' => 'AT', 'Azerbaijan' => 'AZ', 'Bahrain' => 'BH', 'Bangladesh' => 'BD', 'Belarus' => 'BY',
        'Belgium' => 'BE', 'Belize' => 'BZ', 'Benin' => 'BJ', 'Bhutan' => 'BT', 'Bolivia' => 'BO',
        'Bosnia and Herzegovina' => 'BA', 'Botswana' => 'BW', 'Brazil' => 'BR', 'Brunei' => 'BN', 'Bulgaria' => 'BG',
        'Burkina Faso' => 'BF', 'Burundi' => 'BI', 'Cambodia' => 'KH', 'Cameroon' => 'CM', 'Canada' => 'CA',
        'Chad' => 'TD', 'Chile' => 'CL', 'China' => 'CN', 'Colombia' => 'CO', 'Comoros' => 'KM',
        'Costa Rica' => 'CR', 'Croatia' => 'HR', 'Cuba' => 'CU', 'Cyprus' => 'CY', 'Czechia' => 'CZ',
        'Denmark' => 'DK', 'Djibouti' => 'DJ', 'Dominican Republic' => 'DO', 'Ecuador' => 'EC', 'Egypt' => 'EG',
        'El Salvador' => 'SV', 'Eritrea' => 'ER', 'Estonia' => 'EE', 'Ethiopia' => 'ET', 'Fiji' => 'FJ',
        'Finland' => 'FI', 'France' => 'FR', 'Gabon' => 'GA', 'Gambia' => 'GM', 'Georgia' => 'GE',
        'Germany' => 'DE', 'Ghana' => 'GH', 'Greece' => 'GR', 'Grenada' => 'GD', 'Guatemala' => 'GT',
        'Guinea' => 'GN', 'Guyana' => 'GY', 'Haiti' => 'HT', 'Honduras' => 'HN', 'Hungary' => 'HU',
        'Iceland' => 'IS', 'India' => 'IN', 'Indonesia' => 'ID', 'Iran' => 'IR', 'Iraq' => 'IQ',
        'Ireland' => 'IE', 'Italy' => 'IT', 'Ivory Coast' => 'CI', 'Jamaica' => 'JM', 'Japan' => 'JP',
        'Jordan' => 'JO', 'Kazakhstan' => 'KZ', 'Kenya' => 'KE', 'Kuwait' => 'KW', 'Kyrgyzstan' => 'KG',
        'Laos' => 'LA', 'Latvia' => 'LV', 'Lebanon' => 'LB', 'Lesotho' => 'LS', 'Liberia' => 'LR',
        'Libya' => 'LY', 'Lithuania' => 'LT', 'Luxembourg' => 'LU', 'Madagascar' => 'MG', 'Malawi' => 'MW',
        'Malaysia' => 'MY', 'Maldives' => 'MV', 'Mali' => 'ML', 'Malta' => 'MT', 'Mauritania' => 'MR',
        'Mauritius' => 'MU', 'Mexico' => 'MX', 'Moldova' => 'MD', 'Republica Moldova' => 'MD', 'Mongolia' => 'MN',
        'Montenegro' => 'ME', 'Morocco' => 'MA', 'Mozambique' => 'MZ', 'Myanmar' => 'MM', 'Namibia' => 'NA',
        'Nepal' => 'NP', 'Netherlands' => 'NL', 'New Zealand' => 'NZ', 'Niger' => 'NE', 'Nigeria' => 'NG',
        'North Macedonia' => 'MK', 'Norway' => 'NO', 'Oman' => 'OM', 'Pakistan' => 'PK', 'Panama' => 'PA',
        'Papua New Guinea' => 'PG', 'Paraguay' => 'PY', 'Peru' => 'PE', 'Philippines' => 'PH', 'Poland' => 'PL',
        'Portugal' => 'PT', 'Qatar' => 'QA', 'Romania' => 'RO', 'Russia' => 'RU', 'Rwanda' => 'RW',
        'Saudi Arabia' => 'SA', 'Senegal' => 'SN', 'Serbia' => 'RS', 'Seychelles' => 'SC', 'Sierra Leone' => 'SL',
        'Singapore' => 'SG', 'Slovakia' => 'SK', 'Slovenia' => 'SI', 'Somalia' => 'SO', 'South Africa' => 'ZA',
        'South Korea' => 'KR', 'South Sudan' => 'SS', 'Spain' => 'ES', 'Sri Lanka' => 'LK', 'Sudan' => 'SD',
        'Suriname' => 'SR', 'Sweden' => 'SE', 'Switzerland' => 'CH', 'Syria' => 'SY', 'Taiwan' => 'TW',
        'Tajikistan' => 'TJ', 'Tanzania' => 'TZ', 'Thailand' => 'TH', 'Timor-Leste' => 'TL', 'Togo' => 'TG',
        'Trinidad and Tobago' => 'TT', 'Tunisia' => 'TN', 'Turkey' => 'TR', 'Turkmenistan' => 'TM', 'Uganda' => 'UG',
        'Ukraine' => 'UA', 'United Arab Emirates' => 'AE', 'United Kingdom' => 'GB', 'United States' => 'US', 'Uruguay' => 'UY',
        'Uzbekistan' => 'UZ', 'Venezuela' => 'VE', 'Vietnam' => 'VN', 'Yemen' => 'YE', 'Zambia' => 'ZM',
        'Zimbabwe' => 'ZW',
    ];
}

function ec_country_code($name) {
    $map = ec_countries();
    $key = ucwords(strtolower(trim((string)$name)));
    if (isset($map[$key])) return $map[$key];
    foreach ($map as $n => $code) {
        if (strcasecmp($n, trim((string)$name)) === 0) return $code;
    }
    $letters = preg_replace('/[^A-Za-z]/', '', (string)$name);
    return $letters === '' ? 'XX' : strtoupper(substr($letters, 0, 2));
}
