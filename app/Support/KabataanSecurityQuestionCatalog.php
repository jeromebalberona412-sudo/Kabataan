<?php

namespace App\Support;

final class KabataanSecurityQuestionCatalog
{
    public const REQUIRED_COUNT = 3;

    public const IBA_PA = 'Iba pa';

    public const TEXT_MESSAGE = 'Ang sagot ay dapat binubuo lamang ng mga titik at hanggang 15 karakter. Walang espasyo.';

    public const NUMBER_MESSAGE = 'Maglagay lamang ng numerong 0 hanggang 99.';

    /**
     * @return list<array{number: int, text: string, kind: string, choices: list<string>}>
     */
    public static function all(): array
    {
        return [
            self::question(1, 'number', 'Ano ang paborito mong numero?', ['3', '7', '8', '9', '13', '21', '24', '25', '88', '99']),
            self::question(2, 'text', 'Ano ang paborito mong kulay?', ['Asul', 'Pula', 'Berde', 'Dilaw', 'Itim', 'Puti', 'Rosas', 'Lila', 'Kahel', 'Kayumanggi']),
            self::question(3, 'text', 'Ano ang paborito mong hayop?', ['Aso', 'Pusa', 'Kuneho', 'Ibon', 'Isda', 'Kabayo', 'Pagong', 'Paruparo', 'Panda', 'Leon']),
            self::question(4, 'text', 'Ano ang paborito mong pagkain?', ['Adobo', 'Sinigang', 'Tinola', 'Kare-kare', 'Sisig', 'Lechon', 'Pansit', 'Tapsilog', 'Pinakbet', 'Fried Chicken']),
            self::question(5, 'text', 'Ano ang paborito mong inumin?', ['Tubig', 'Gatas', 'Kape', 'Tsaa', 'Tsokolate', 'Kalamansi', 'Lemonada', 'Iced Tea', 'Milk Tea', 'Juice']),
            self::question(6, 'text', 'Ano ang paborito mong uri ng pelikula?', ['Aksyon', 'Komedya', 'Katatakutan', 'Romansa', 'Pakikipagsapalaran', 'Animasyon', 'Pantasya', 'Siyensiyang Piksiyon', 'Misteryo', 'Dokumentaryo']),
            self::question(7, 'text', 'Ano ang paborito mong palakasan?', ['Basketbol', 'Volleyball', 'Badminton', 'Futbol', 'Langoy', 'Takbuhan', 'Pagbibisikleta', 'Boksing', 'Table Tennis', 'Tennis']),
            self::question(8, 'text', 'Ano ang paborito mong uri ng musika?', ['OPM', 'Pop', 'Rock', 'Rap', 'R&B', 'K-Pop', 'Jazz', 'Classical', 'Folk', 'Instrumental']),
            self::question(9, 'text', 'Ano ang paborito mong araw ng linggo?', ['Lunes', 'Martes', 'Miyerkules', 'Huwebes', 'Biyernes', 'Sabado', 'Linggo', 'Lahat', 'Wala', 'Depende']),
            self::question(10, 'text', 'Ano ang paborito mong buwan?', ['Enero', 'Pebrero', 'Marso', 'Abril', 'Mayo', 'Hunyo', 'Hulyo', 'Agosto', 'Setyembre', 'Disyembre']),
            self::question(11, 'text', 'Ano ang paborito mong prutas?', ['Mangga', 'Saging', 'Mansanas', 'Kahel', 'Pakwan', 'Ubas', 'Pinya', 'Papaya', 'Bayabas', 'Melon']),
            self::question(12, 'text', 'Ano ang paborito mong asignatura?', ['Matematika', 'Agham', 'Ingles', 'Filipino', 'Araling Panlipunan', 'Edukasyon sa Pagpapakatao', 'MAPEH', 'Teknolohiya', 'Pisikal na Edukasyon', 'Computer']),
            self::question(13, 'text', 'Ano ang paborito mong lugar na puntahan?', ['Dalampasigan', 'Parke', 'Pamilihan', 'Kabundukan', 'Mall', 'Museo', 'Palaruan', 'Simbahan', 'Turistang Lugar', 'Bahay']),
            self::question(14, 'text', 'Ano ang iyong palayaw noong bata ka?', ['Junior', 'Toto', 'Nene', 'Boy', 'Baby', 'Bebe', 'Bunso', 'Inday', 'Dodong', 'Wala']),
            self::question(15, 'own', 'Ano ang apelyido ng iyong ina bago siya ikasal?', []),
        ];
    }

    /**
     * @return array{number: int, text: string, kind: string, choices: list<string>}|null
     */
    public static function find(int $number): ?array
    {
        foreach (self::all() as $question) {
            if ($question['number'] === $number) {
                return $question;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $choices
     * @return array{number: int, text: string, kind: string, choices: list<string>}
     */
    private static function question(int $number, string $kind, string $text, array $choices): array
    {
        return [
            'number' => $number,
            'text' => $text,
            'kind' => $kind,
            'choices' => $choices,
        ];
    }
}
