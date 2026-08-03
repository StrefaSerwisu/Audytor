<?php

namespace Database\Seeders;

use App\Models\Audit;
use App\Models\AuditModule;
use App\Models\AuditQuestion;
use App\Models\AuditTemplate;
use App\Models\Client;
use App\Models\ClientLocation;
use App\Models\Recommendation;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $users = [
            ['name' => 'Super Admin', 'email' => 'superadmin@globalit.test', 'role' => 'super_admin'],
            ['name' => 'Administrator Global IT', 'email' => 'admin@globalit.test', 'role' => 'global_admin'],
            ['name' => 'Lider Techniczny', 'email' => 'lider@globalit.test', 'role' => 'technical_lead'],
            ['name' => 'Audytor Global IT', 'email' => 'audytor@globalit.test', 'role' => 'auditor'],
            ['name' => 'Sales Global IT', 'email' => 'sales@globalit.test', 'role' => 'sales'],
        ];

        foreach ($users as $user) {
            User::updateOrCreate(
                ['email' => $user['email']],
                [
                    'name' => $user['name'],
                    'password' => Hash::make('password'),
                    'role' => $user['role'],
                    'mfa_enabled' => false,
                    'active' => true,
                ],
            );
        }

        $client = Client::updateOrCreate(
            ['name' => 'Klient Testowy Sp. z o.o.'],
            [
                'nip' => '1234567890',
                'address' => 'ul. Testowa 1, 00-001 Warszawa',
                'contact_name' => 'Jan Kowalski',
                'contact_email' => 'kontakt@klient-testowy.test',
                'contact_phone' => '+48 500 100 200',
                'account_manager_id' => User::where('email', 'sales@globalit.test')->value('id'),
                'status' => 'active',
                'notes' => 'Klient testowy dla Etapu 1 aplikacji Audytor IT.',
            ],
        );

        User::updateOrCreate(
            ['email' => 'klient@globalit.test'],
            [
                'name' => 'Klient Testowy',
                'password' => Hash::make('password'),
                'role' => 'client',
                'client_id' => $client->id,
                'mfa_enabled' => false,
                'active' => true,
            ],
        );

        foreach ([
            ['name' => 'Centrala', 'location_type' => 'office', 'address' => 'ul. Testowa 1, 00-001 Warszawa'],
            ['name' => 'Oddział', 'location_type' => 'branch', 'address' => 'ul. Oddziałowa 5, 30-001 Kraków'],
            ['name' => 'Serwerownia', 'location_type' => 'server_room', 'address' => 'ul. Testowa 1, poziom -1, Warszawa'],
        ] as $location) {
            ClientLocation::updateOrCreate(
                [
                    'client_id' => $client->id,
                    'name' => $location['name'],
                ],
                [
                    'location_type' => $location['location_type'],
                    'address' => $location['address'],
                    'contact_name' => 'Jan Kowalski',
                    'contact_email' => 'kontakt@klient-testowy.test',
                    'contact_phone' => '+48 500 100 200',
                    'notes' => 'Dane testowe dla audytu Global IT.',
                ],
            );
        }

        $modules = [
            ['name' => 'UTM/firewall', 'category' => 'security', 'sort_order' => 10, 'description' => 'Urzadzenie brzegowe, firmware, licencje, VPN i polityki firewall.'],
            ['name' => 'Switche', 'category' => 'network', 'sort_order' => 20, 'description' => 'Topologia, VLAN, dostep administracyjny, PoE i backup konfiguracji.'],
            ['name' => 'Serwery', 'category' => 'servers', 'sort_order' => 30, 'description' => 'Sprzet, systemy, role, gwarancje i monitoring.'],
            ['name' => 'Microsoft 365', 'category' => 'cloud', 'sort_order' => 40, 'description' => 'MFA, role adminow, backup, licencje i Conditional Access.'],
            ['name' => 'Backup', 'category' => 'security', 'sort_order' => 50, 'description' => 'Zakres backupu, retencja, testy odtworzeniowe i alerty.'],
        ];

        foreach ($modules as $module) {
            AuditModule::updateOrCreate(
                ['name' => $module['name']],
                [
                    'category' => $module['category'],
                    'description' => $module['description'],
                    'sort_order' => $module['sort_order'],
                    'active' => true,
                ],
            );
        }

        $template = AuditTemplate::updateOrCreate(
            ['name' => 'Audyt podstawowy IT'],
            [
                'description' => 'Bazowy szablon audytu Global IT obejmujacy kluczowe obszary infrastruktury.',
                'active' => true,
            ],
        );

        foreach ($modules as $index => $module) {
            $template->templateModules()->updateOrCreate(
                ['audit_module_id' => AuditModule::where('name', $module['name'])->value('id')],
                ['sort_order' => ($index + 1) * 10],
            );
        }

        $utmModule = AuditModule::where('name', 'UTM/firewall')->firstOrFail();

        $utmQuestions = [
            [
                'question' => 'Podaj producenta i model urzadzenia.',
                'instruction' => 'Sprawdz etykiete urzadzenia albo dashboard administracyjny.',
                'field_type' => 'short_text',
                'is_required' => true,
                'sort_order' => 10,
            ],
            [
                'question' => 'Podaj numer seryjny.',
                'instruction' => 'Numer seryjny powinien byc zgodny z etykieta urzadzenia lub panelem producenta.',
                'field_type' => 'short_text',
                'is_required' => true,
                'sort_order' => 20,
            ],
            [
                'question' => 'Podaj wersje firmware.',
                'instruction' => 'Zapisz pelna wersje firmware widoczna w panelu administracyjnym.',
                'field_type' => 'short_text',
                'is_required' => true,
                'sort_order' => 30,
                'require_screenshot' => true,
            ],
            [
                'question' => 'Czy firmware jest aktualny?',
                'instruction' => 'Porownaj wersje z aktualna wersja producenta albo informacja w panelu.',
                'field_type' => 'yes_no',
                'is_required' => true,
                'sort_order' => 40,
                'risk_enabled' => true,
            ],
            [
                'question' => 'Czy panel administracyjny jest dostepny z WAN?',
                'instruction' => 'Zweryfikuj reguly dostepu administracyjnego i ograniczenia zrodlowe.',
                'field_type' => 'yes_no',
                'is_required' => true,
                'sort_order' => 50,
                'require_screenshot' => true,
                'risk_enabled' => true,
            ],
            [
                'question' => 'Czy aktywne sa licencje bezpieczenstwa?',
                'instruction' => 'Sprawdz status subskrypcji UTM, filtrow, IPS, AV i wsparcia.',
                'field_type' => 'yes_no',
                'is_required' => true,
                'sort_order' => 60,
                'require_screenshot' => true,
            ],
            [
                'question' => 'Czy konfiguracja jest backupowana?',
                'instruction' => 'Sprawdz, gdzie i jak czesto wykonywany jest backup konfiguracji.',
                'field_type' => 'yes_no',
                'is_required' => true,
                'sort_order' => 70,
                'risk_enabled' => true,
            ],
            [
                'question' => 'Czy dane dostepowe sa zapisane w Keeper?',
                'instruction' => 'Nie zapisuj hasel w audycie. Potwierdz tylko obecnosc wpisu w Keeper.',
                'field_type' => 'yes_no',
                'is_required' => true,
                'sort_order' => 80,
            ],
            [
                'question' => 'Czy skonfigurowane sa VPN?',
                'instruction' => 'Wymien typy VPN i sprawdz, czy konta oraz polityki sa aktualne.',
                'field_type' => 'long_text',
                'is_required' => true,
                'sort_order' => 90,
            ],
            [
                'question' => 'Dodaj zdjecie urzadzenia w szafie rack.',
                'instruction' => 'Zdjecie powinno pokazywac urzadzenie i okablowanie bez ujawniania hasel.',
                'field_type' => 'photo',
                'is_required' => true,
                'sort_order' => 100,
                'require_photo' => true,
            ],
            [
                'question' => 'Dodaj screenshot dashboardu.',
                'instruction' => 'Screenshot powinien pokazac stan urzadzenia bez danych wrazliwych.',
                'field_type' => 'screenshot',
                'is_required' => true,
                'sort_order' => 110,
                'require_screenshot' => true,
            ],
            [
                'question' => 'Dodaj screenshot polityk firewall.',
                'instruction' => 'Pokaz zakres polityk bez eksportowania sekretow i kluczy.',
                'field_type' => 'screenshot',
                'is_required' => true,
                'sort_order' => 120,
                'require_screenshot' => true,
            ],
            [
                'question' => 'Ocen ryzyko.',
                'instruction' => 'Wybierz poziom ryzyka dla obszaru UTM/firewall.',
                'field_type' => 'risk_level',
                'is_required' => true,
                'sort_order' => 130,
                'risk_enabled' => true,
            ],
            [
                'question' => 'Dodaj rekomendacje.',
                'instruction' => 'Dla ryzyka wysokiego albo krytycznego rekomendacja bedzie wymagana w Etapie 8.',
                'field_type' => 'long_text',
                'is_required' => true,
                'sort_order' => 140,
            ],
        ];

        foreach ($utmQuestions as $question) {
            AuditQuestion::updateOrCreate(
                [
                    'audit_module_id' => $utmModule->id,
                    'question' => $question['question'],
                ],
                [
                    'instruction' => $question['instruction'],
                    'field_type' => $question['field_type'],
                    'is_required' => $question['is_required'],
                    'allow_not_applicable' => true,
                    'require_comment_when_na' => true,
                    'require_photo' => $question['require_photo'] ?? false,
                    'require_screenshot' => $question['require_screenshot'] ?? false,
                    'risk_enabled' => $question['risk_enabled'] ?? false,
                    'sort_order' => $question['sort_order'],
                    'config_json' => null,
                    'active' => true,
                ],
            );
        }

        $moduleQuestionSets = [
            'Switche' => [
                [
                    'question' => 'Podaj producenta i modele przelacznikow.',
                    'instruction' => 'Wymien modele glownych switchy oraz liczbe urzadzen w lokalizacji.',
                    'field_type' => 'long_text',
                    'is_required' => true,
                    'sort_order' => 10,
                ],
                [
                    'question' => 'Czy konfiguracje switchy sa backupowane?',
                    'instruction' => 'Zweryfikuj, gdzie przechowywane sa kopie konfiguracji i jak czesto sa aktualizowane.',
                    'field_type' => 'yes_no',
                    'is_required' => true,
                    'sort_order' => 20,
                    'risk_enabled' => true,
                ],
                [
                    'question' => 'Czy VLAN-y i segmentacja sieci sa udokumentowane?',
                    'instruction' => 'Sprawdz dokumentacje VLAN, podsieci, trunkow i separacji sieci goscinnej.',
                    'field_type' => 'yes_no',
                    'is_required' => true,
                    'sort_order' => 30,
                    'risk_enabled' => true,
                ],
                [
                    'question' => 'Czy dostep administracyjny do switchy jest ograniczony?',
                    'instruction' => 'Zweryfikuj konta administracyjne, ACL, SSH/HTTPS i brak domyslnych hasel.',
                    'field_type' => 'yes_no',
                    'is_required' => true,
                    'sort_order' => 40,
                    'risk_enabled' => true,
                ],
                [
                    'question' => 'Dodaj screenshot konfiguracji VLAN lub listy switchy.',
                    'instruction' => 'Screenshot powinien potwierdzac konfiguracje bez ujawniania sekretow.',
                    'field_type' => 'screenshot',
                    'is_required' => true,
                    'sort_order' => 50,
                    'require_screenshot' => true,
                ],
                [
                    'question' => 'Ocen ryzyko dla infrastruktury switchy.',
                    'instruction' => 'Uwzglednij segmentacje, backup konfiguracji, gwarancje i dostep administracyjny.',
                    'field_type' => 'risk_level',
                    'is_required' => true,
                    'sort_order' => 60,
                    'risk_enabled' => true,
                ],
            ],
            'Serwery' => [
                [
                    'question' => 'Wymien kluczowe serwery i ich role.',
                    'instruction' => 'Podaj nazwy, role, systemy operacyjne oraz lokalizacje fizyczna lub wirtualna.',
                    'field_type' => 'long_text',
                    'is_required' => true,
                    'sort_order' => 10,
                ],
                [
                    'question' => 'Czy systemy operacyjne serwerow sa wspierane?',
                    'instruction' => 'Zweryfikuj wersje OS, daty konca wsparcia i aktualizacje krytyczne.',
                    'field_type' => 'yes_no',
                    'is_required' => true,
                    'sort_order' => 20,
                    'risk_enabled' => true,
                ],
                [
                    'question' => 'Czy serwery sa monitorowane?',
                    'instruction' => 'Sprawdz monitoring dostepnosci, zasobow, dyskow, uslug i alertow.',
                    'field_type' => 'yes_no',
                    'is_required' => true,
                    'sort_order' => 30,
                    'risk_enabled' => true,
                ],
                [
                    'question' => 'Czy gwarancje lub umowy serwisowe sa aktywne?',
                    'instruction' => 'Zweryfikuj gwarancje sprzetowe, SLA i dostep do wsparcia producenta.',
                    'field_type' => 'yes_no',
                    'is_required' => true,
                    'sort_order' => 40,
                ],
                [
                    'question' => 'Dodaj screenshot dashboardu monitoringu serwerow.',
                    'instruction' => 'Screenshot powinien pokazac status uslug lub zasobow bez danych wrazliwych.',
                    'field_type' => 'screenshot',
                    'is_required' => true,
                    'sort_order' => 50,
                    'require_screenshot' => true,
                ],
                [
                    'question' => 'Ocen ryzyko dla obszaru serwerow.',
                    'instruction' => 'Uwzglednij wsparcie OS, monitoring, backup i pojedyncze punkty awarii.',
                    'field_type' => 'risk_level',
                    'is_required' => true,
                    'sort_order' => 60,
                    'risk_enabled' => true,
                ],
            ],
            'Microsoft 365' => [
                [
                    'question' => 'Czy MFA jest wymuszone dla kont administracyjnych Microsoft 365?',
                    'instruction' => 'Sprawdz ustawienia MFA i polityki Conditional Access dla administratorow.',
                    'field_type' => 'yes_no',
                    'is_required' => true,
                    'sort_order' => 10,
                    'risk_enabled' => true,
                    'require_screenshot' => true,
                ],
                [
                    'question' => 'Czy liczba global adminow jest ograniczona?',
                    'instruction' => 'Zweryfikuj role administracyjne i zasade minimalnych uprawnien.',
                    'field_type' => 'yes_no',
                    'is_required' => true,
                    'sort_order' => 20,
                    'risk_enabled' => true,
                ],
                [
                    'question' => 'Czy wdrozono niezalezny backup Microsoft 365?',
                    'instruction' => 'Sprawdz backup Exchange Online, OneDrive, SharePoint i Teams.',
                    'field_type' => 'yes_no',
                    'is_required' => true,
                    'sort_order' => 30,
                    'risk_enabled' => true,
                ],
                [
                    'question' => 'Czy licencje Microsoft 365 sa zgodne z realnym uzyciem?',
                    'instruction' => 'Porownaj przypisane licencje, nieaktywne konta i potrzeby uzytkownikow.',
                    'field_type' => 'yes_no',
                    'is_required' => true,
                    'sort_order' => 40,
                ],
                [
                    'question' => 'Dodaj screenshot ustawien bezpieczenstwa Microsoft 365.',
                    'instruction' => 'Screenshot powinien potwierdzac MFA, role lub Conditional Access bez ujawniania danych wrazliwych.',
                    'field_type' => 'screenshot',
                    'is_required' => true,
                    'sort_order' => 50,
                    'require_screenshot' => true,
                ],
                [
                    'question' => 'Ocen ryzyko dla Microsoft 365.',
                    'instruction' => 'Uwzglednij MFA, role adminow, backup, licencje i ochrone danych.',
                    'field_type' => 'risk_level',
                    'is_required' => true,
                    'sort_order' => 60,
                    'risk_enabled' => true,
                ],
            ],
            'Backup' => [
                [
                    'question' => 'Jakie systemy sa objete backupem?',
                    'instruction' => 'Wymien serwery, stacje, dane M365, bazy danych i systemy krytyczne.',
                    'field_type' => 'long_text',
                    'is_required' => true,
                    'sort_order' => 10,
                ],
                [
                    'question' => 'Czy retencja backupu odpowiada wymaganiom biznesowym?',
                    'instruction' => 'Sprawdz czas przechowywania kopii i dopasowanie do RPO/RTO.',
                    'field_type' => 'yes_no',
                    'is_required' => true,
                    'sort_order' => 20,
                    'risk_enabled' => true,
                ],
                [
                    'question' => 'Czy wykonywane sa testy odtworzeniowe?',
                    'instruction' => 'Zweryfikuj daty ostatnich testow restore i dokumentacje wynikow.',
                    'field_type' => 'yes_no',
                    'is_required' => true,
                    'sort_order' => 30,
                    'risk_enabled' => true,
                ],
                [
                    'question' => 'Czy backup jest chroniony przed ransomware?',
                    'instruction' => 'Sprawdz immutable storage, separacje uprawnien, MFA i kopie offline/offsite.',
                    'field_type' => 'yes_no',
                    'is_required' => true,
                    'sort_order' => 40,
                    'risk_enabled' => true,
                ],
                [
                    'question' => 'Dodaj screenshot konsoli backupu.',
                    'instruction' => 'Screenshot powinien pokazac status zadan backupu bez ujawniania sekretow.',
                    'field_type' => 'screenshot',
                    'is_required' => true,
                    'sort_order' => 50,
                    'require_screenshot' => true,
                ],
                [
                    'question' => 'Ocen ryzyko dla obszaru backupu.',
                    'instruction' => 'Uwzglednij zakres, retencje, testy restore, alerty i odpornosc na ransomware.',
                    'field_type' => 'risk_level',
                    'is_required' => true,
                    'sort_order' => 60,
                    'risk_enabled' => true,
                ],
            ],
        ];

        foreach ($moduleQuestionSets as $moduleName => $questions) {
            $module = AuditModule::where('name', $moduleName)->firstOrFail();

            foreach ($questions as $question) {
                AuditQuestion::updateOrCreate(
                    [
                        'audit_module_id' => $module->id,
                        'question' => $question['question'],
                    ],
                    [
                        'instruction' => $question['instruction'],
                        'field_type' => $question['field_type'],
                        'is_required' => $question['is_required'],
                        'allow_not_applicable' => true,
                        'require_comment_when_na' => true,
                        'require_photo' => $question['require_photo'] ?? false,
                        'require_screenshot' => $question['require_screenshot'] ?? false,
                        'risk_enabled' => $question['risk_enabled'] ?? false,
                        'sort_order' => $question['sort_order'],
                        'config_json' => null,
                        'active' => true,
                    ],
                );
            }
        }

        $recommendations = [
            [
                'title' => 'Aktualizacja firmware UTM',
                'technical_description' => 'Urzadzenie UTM powinno pracowac na wspieranej wersji firmware producenta. Nieaktualny firmware moze zawierac znane podatnosci i bledy stabilnosci.',
                'business_description' => 'Nieaktualne oprogramowanie urzadzenia brzegowego zwieksza ryzyko incydentu bezpieczenstwa oraz przerwy w dostepie do internetu lub uslug zdalnych.',
                'recommendation_text' => 'Zalecamy zaplanowanie aktualizacji firmware UTM, wykonanie backupu konfiguracji przed zmiana oraz weryfikacje poprawnosci pracy po aktualizacji.',
                'risk_level' => 'high',
                'priority' => 'high',
                'suggested_deadline' => 'Do 30 dni',
                'estimated_hours_min' => 2,
                'estimated_hours_max' => 4,
                'global_it_can_do' => true,
                'sales_category' => 'Network Security / UTM',
                'tags_json' => ['utm' => 'true', 'firmware' => 'true'],
                'questions' => [
                    'Podaj wersje firmware.',
                    'Czy firmware jest aktualny?',
                ],
            ],
            [
                'title' => 'Wylaczenie dostepu administracyjnego z WAN',
                'technical_description' => 'Panel administracyjny UTM nie powinien byc publicznie dostepny z internetu bez silnych ograniczen zrodlowych i dodatkowych zabezpieczen.',
                'business_description' => 'Publiczny dostep administracyjny do urzadzenia brzegowego zwieksza ryzyko przejecia konfiguracji sieci i przerwy operacyjnej.',
                'recommendation_text' => 'Zalecamy wylaczenie dostepu administracyjnego z WAN albo ograniczenie go do zaufanych adresow IP oraz wymuszenie MFA, jesli urzadzenie to wspiera.',
                'risk_level' => 'critical',
                'priority' => 'critical',
                'suggested_deadline' => 'Niezwlocznie',
                'estimated_hours_min' => 1,
                'estimated_hours_max' => 2,
                'global_it_can_do' => true,
                'sales_category' => 'Network Security / Hardening',
                'tags_json' => ['utm' => 'true', 'wan_admin' => 'true'],
                'questions' => [
                    'Czy panel administracyjny jest dostepny z WAN?',
                ],
            ],
            [
                'title' => 'Wdrozenie backupu konfiguracji UTM',
                'technical_description' => 'Konfiguracja urzadzenia UTM powinna byc regularnie backupowana i przechowywana w bezpiecznym miejscu.',
                'business_description' => 'Brak backupu konfiguracji wydluza odtworzenie po awarii i moze zwiekszyc czas przestoju firmy.',
                'recommendation_text' => 'Zalecamy wdrozenie cyklicznego backupu konfiguracji UTM oraz okresowa weryfikacje mozliwosci odtworzenia.',
                'risk_level' => 'medium',
                'priority' => 'medium',
                'suggested_deadline' => 'Do 60 dni',
                'estimated_hours_min' => 1,
                'estimated_hours_max' => 3,
                'global_it_can_do' => true,
                'sales_category' => 'Network Operations',
                'tags_json' => ['utm' => 'true', 'backup' => 'true'],
                'questions' => [
                    'Czy konfiguracja jest backupowana?',
                ],
            ],
            [
                'title' => 'Wymuszenie MFA dla administratorow M365',
                'technical_description' => 'Konta administracyjne Microsoft 365 powinny miec wymuszone uwierzytelnianie wieloskladnikowe oraz polityki Conditional Access.',
                'business_description' => 'Przejecie konta administratora Microsoft 365 moze skutkowac utrata danych, wyciekiem informacji, przestojem operacyjnym oraz kosztami obslugi incydentu.',
                'recommendation_text' => 'Zalecamy wymuszenie MFA dla wszystkich kont administracyjnych oraz wdrozenie polityk Conditional Access.',
                'risk_level' => 'critical',
                'priority' => 'critical',
                'suggested_deadline' => 'Niezwlocznie',
                'estimated_hours_min' => 2,
                'estimated_hours_max' => 4,
                'global_it_can_do' => true,
                'sales_category' => 'Microsoft 365 / Security',
                'tags_json' => ['m365' => 'true', 'mfa' => 'true'],
                'questions' => [
                    'Czy MFA jest wymuszone dla kont administracyjnych Microsoft 365?',
                    'Czy liczba global adminow jest ograniczona?',
                ],
            ],
            [
                'title' => 'Wdrozenie backupu Microsoft 365',
                'technical_description' => 'Dane Microsoft 365 powinny byc chronione niezaleznym backupem obejmujacym Exchange Online, SharePoint, OneDrive i Teams.',
                'business_description' => 'Brak niezaleznego backupu Microsoft 365 zwieksza ryzyko trwalej utraty danych po usunieciu, bledzie uzytkownika lub incydencie ransomware.',
                'recommendation_text' => 'Zalecamy wdrozenie uslugi backupu Microsoft 365 z retencja dopasowana do wymagan biznesowych klienta.',
                'risk_level' => 'high',
                'priority' => 'high',
                'suggested_deadline' => 'Do 30 dni',
                'estimated_hours_min' => 4,
                'estimated_hours_max' => 8,
                'global_it_can_do' => true,
                'sales_category' => 'BaaS / Microsoft 365 Backup',
                'tags_json' => ['m365' => 'true', 'backup' => 'true'],
                'questions' => [
                    'Czy wdrozono niezalezny backup Microsoft 365?',
                    'Jakie systemy sa objete backupem?',
                ],
            ],
        ];

        foreach ($recommendations as $recommendationData) {
            $questionTitles = $recommendationData['questions'];
            unset($recommendationData['questions']);

            $recommendation = Recommendation::updateOrCreate(
                ['title' => $recommendationData['title']],
                [
                    ...$recommendationData,
                    'active' => true,
                ],
            );

            foreach ($questionTitles as $questionTitle) {
                $question = AuditQuestion::where('question', $questionTitle)->first();

                if ($question) {
                    $recommendation->questions()->syncWithoutDetaching([$question->id]);
                }
            }
        }

        $audit = Audit::updateOrCreate(
            ['title' => 'Audyt podstawowy IT - Klient Testowy'],
            [
                'client_id' => $client->id,
                'client_location_id' => ClientLocation::where('client_id', $client->id)
                    ->where('name', 'Centrala')
                    ->value('id'),
                'audit_template_id' => $template->id,
                'description' => 'Testowy audyt utworzony w Etapie 5 na podstawie szablonu Audyt podstawowy IT.',
                'status' => 'scheduled',
                'scheduled_at' => now()->addWeek()->setTime(9, 0),
                'created_by' => User::where('email', 'admin@globalit.test')->value('id'),
                'lead_reviewer_id' => User::where('email', 'lider@globalit.test')->value('id'),
            ],
        );

        foreach ($template->templateModules()->orderBy('sort_order')->get() as $templateModule) {
            $audit->selectedModules()->updateOrCreate(
                ['audit_module_id' => $templateModule->audit_module_id],
                ['sort_order' => $templateModule->sort_order],
            );
        }

        foreach ([
            ['email' => 'audytor@globalit.test', 'role_in_audit' => 'auditor'],
            ['email' => 'lider@globalit.test', 'role_in_audit' => 'lead'],
        ] as $assignee) {
            $audit->assignees()->updateOrCreate(
                ['user_id' => User::where('email', $assignee['email'])->value('id')],
                ['role_in_audit' => $assignee['role_in_audit']],
            );
        }

        $this->call(DemoPricingRulesSeeder::class);
    }
}
