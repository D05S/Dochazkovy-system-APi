<?php

namespace App\Http\Controllers;

use App\Models\Record;
use DOMDocument;
use DOMXPath;
use Illuminate\Http\Request;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class RecordController extends Controller
{
    public function get(Request $request) {
        $validator = $this->validateRequest();

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $content = file_get_contents($request->get('url'));
        $encode = json_encode($content);

        dd(explode('<hr width="100%">', $content));

        $table = explode('<hr width="100%">', $content)[1];
        $table = strtr($table, $this->formatText());
        $table = str_replace("&nbsp;", "", $table);

        // Plain text without HTML
        $text = strip_tags($encode);

        // Cleaning process
        $clean = str_replace("&nbsp;", "", $text);
        $clean = str_replace('\n', "", $clean);
        $final = str_replace(" ", "", $clean);

        $format = strtr($final, $this->formatText());

        $date = date("d.m.Y H:i:s", strtotime(explode("Období", explode("Výkazprácez;", $format)[1])[0]));
        $obdobi = explode("Osobníčíslo", explode("Období:", $format)[1])[0];
        $osobniCislo = explode("Pracovník:", explode("Osobníčíslo:", $format)[1])[0];
        $jmeno = explode("Středisko:", explode("Pracovník:", $format)[1])[0];
        $stredisko = explode("Fondprac.doby", explode("Středisko:", $format)[1])[0];

        $prescas = explode("hodin", explode("Přesčashodiny", $format)[1])[0];
        $pracovnichDnu = explode("dnů", explode("Celkemodpracovánodny", $format)[1])[0];
        $odpracovanoDni = explode("dnů", explode("Skutečněodpracovánodny", $format)[1])[0];
        $lekar = explode("dnů", explode("Lékařdny", $format)[1])[0];
        $dovolena = explode("dnů", explode("Dovolenádny", $format)[1])[0];

        $records = $this->tableToJson('<html><head><meta charset="UTF-8"></head><body>' . $table . '</body></html>');
        unset($records[0]);
        $records = array_values($records);
        $response = [];

        foreach ($records as $key => $record) {

            $day = [
                'date' => $record[0],
                'day' => $record[1],
                'start' => $record[2],
                'end' => $record[3],
                'time' => strtotime($record[4]),
                'reason' => $record[5]
            ];

            if($this->find($response, $day['date']) === false) {
                $response[] = $day;
            }

            if ($key+1 < count($records)) {
                if (strtotime($day['date']) === strtotime($records[$key+1][0])) {
                    $r = $this->find($response, $day['date']);
                    if ($records[$key+1][5] === "") {
                        $response[$r]["time"] =+ strtotime($records[$key+1][4]);
                    }
                    $response[$r]["end"] = $records[$key+1][3];
                }
            }
        }

        $array = [
            'datum' => $date,
            'obdobi' => $obdobi,
            'osobni_cislo' => $osobniCislo,
            'jmeno' => $jmeno,
            'stredisko' => $stredisko,
            'prescas' => $prescas,
            'pracovnichDnuCelkem' => $pracovnichDnu,
            'odpracovanoDnu' => $odpracovanoDni,
            'lekar' => $lekar,
            'dovolena' => $dovolena,
            'times' => $response
        ];

        return response()->json(['data' => $array], 200);
    }

    public function find($days, $date) {
        foreach($days as $key => $day) {
            if($day['date'] == $date) return $key;
        }
        return FALSE;
    }

    public function formatText()
    {
        return array(
            "\u00c0" =>"À",
            "\u00c1" =>"Á",
            "\u00c2" =>"Â",
            "\u00c3" =>"Ã",
            "\u00c4" =>"Ä",
            "\u00c5" =>"Å",
            "\u00c6" =>"Æ",
            "\u00c7" =>"Ç",
            "\u00c8" =>"È",
            "\u00c9" =>"É",
            "\u00ca" =>"Ê",
            "\u00cb" =>"Ë",
            "\u00cc" =>"Ì",
            "\u00cd" =>"Í",
            "\u00ce" =>"Î",
            "\u00cf" =>"Ï",
            "\u00d1" =>"Ñ",
            "\u00d2" =>"Ò",
            "\u00d3" =>"Ó",
            "\u00d4" =>"Ô",
            "\u00d5" =>"Õ",
            "\u00d6" =>"Ö",
            "\u00d8" =>"Ø",
            "\u00d9" =>"Ù",
            "\u00da" =>"Ú",
            "\u00db" =>"Û",
            "\u00dc" =>"Ü",
            "\u00dd" =>"Ý",
            "\u00df" =>"ß",
            "\u00e0" =>"à",
            "\u00e1" =>"á",
            "\u00e2" =>"â",
            "\u00e3" =>"ã",
            "\u00e4" =>"ä",
            "\u00e5" =>"å",
            "\u00e6" =>"æ",
            "\u00e7" =>"ç",
            "\u00e8" =>"è",
            "\u00e9" =>"é",
            "\u011b" =>"ě",
            "\u00ea" =>"ê",
            "\u00eb" =>"ë",
            "\u00ec" =>"ì",
            "\u00ed" =>"í",
            "\u00ee" =>"î",
            "\u00ef" =>"ï",
            "\u00f0" =>"ð",
            "\u00f1" =>"ñ",
            "\u00f2" =>"ò",
            "\u00f3" =>"ó",
            "\u00f4" =>"ô",
            "\u00f5" =>"õ",
            "\u00f6" =>"ö",
            "\u00f8" =>"ø",
            "\u00f9" =>"ù",
            "\u00fa" =>"ú",
            "\u00fb" =>"û",
            "\u00fc" =>"ü",
            "\u00fd" =>"ý",
            "\u00ff" =>"ÿ",
            "\u0159" =>"ř",
            "\u010d" =>"č",
            "\u017e" =>"ž",
            "\u016f" =>"ů",
            "\u0161" =>"š",
            "\u0160" =>"Š",
            "\u010c" => "Č"
        );
    }

    public function tableToJson($string)
    {
        $dom = new DOMDocument();
        $dom->loadHTML($string);
        $xpath = new DOMXPath($dom);

        foreach ($xpath->query('//tbody/tr') as $tr) {
            $tmp = [];

            foreach ($xpath->query("td", $tr) as $td) {
                $tmp[] = trim($td->textContent);
            }

            $result[] = $tmp;
        }

        return $result;
    }

    private function validateRequest(){
        return Validator::make(request()->all(), [
            'pathInfo' => 'required',
            'nameOfUser' => 'required',
            'number' => 'required',
            'department' => 'required'
        ]);
    }
}
