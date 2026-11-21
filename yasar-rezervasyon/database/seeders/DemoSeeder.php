<?php

namespace Database\Seeders;

use App\Models\Equipment;
use App\Models\Facility;
use App\Models\KvkkText;
use App\Models\Team;
use App\Models\User;
use App\Services\Ldap\Directory;
use App\Services\UserDirectory;
use App\Support\Roles;
use Illuminate\Database\Seeder;

/** Yalnızca geliştirme/test: örnek tesisler, takım ve sahte LDAP kullanıcıları. */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $everyone = Roles::BOOKING;
        $staffAndCoach = [Roles::ADMIN_STAFF, Roles::ACADEMIC, Roles::COACH];

        $gym = Facility::create([
            'name_tr' => 'Spor Salonu', 'name_en' => 'Sports Hall', 'slot_minutes' => 60, 'capacity' => 30,
            'group_allowed' => true, 'participant_list_required' => false, 'cancel_deadline_hours' => 3,
            'allowed_roles' => $everyone, 'sort' => 1,
            'description_tr' => 'Voleybolda tüm salon, basketbolda tek pota veya takım için iki pota rezerve edilir.',
            'description_en' => 'Volleyball books the whole hall; basketball books one hoop, or two hoops for teams.',
        ]);
        $gym->units()->createMany([['name_tr' => 'Pota A', 'name_en' => 'Hoop A', 'sort' => 1], ['name_tr' => 'Pota B', 'name_en' => 'Hoop B', 'sort' => 2]]);
        $gym->activities()->createMany([
            ['name_tr' => 'Basketbol (Bireysel)', 'name_en' => 'Basketball (Individual)', 'units_required' => 1, 'allowed_roles' => $everyone],
            ['name_tr' => 'Basketbol (Takım)', 'name_en' => 'Basketball (Team)', 'units_required' => 2, 'is_team' => true, 'allowed_roles' => $staffAndCoach],
            ['name_tr' => 'Voleybol', 'name_en' => 'Volleyball', 'units_required' => 2, 'allowed_roles' => $staffAndCoach],
        ]);

        $dance = Facility::create([
            'name_tr' => 'Dans Salonu', 'name_en' => 'Dance Studio', 'slot_minutes' => 60, 'capacity' => 20,
            'group_allowed' => true, 'participant_list_required' => true, 'cancel_deadline_hours' => 2,
            'allowed_roles' => $everyone, 'sort' => 2,
        ]);
        $dance->units()->create(['name_tr' => 'Salon', 'name_en' => 'Studio']);
        $dance->activities()->create(['name_tr' => 'Dans / Pilates', 'name_en' => 'Dance / Pilates', 'units_required' => 1, 'allowed_roles' => $everyone]);

        $study = Facility::create([
            'name_tr' => 'Çalışma Odası 1', 'name_en' => 'Study Room 1', 'slot_minutes' => 30, 'capacity' => 6,
            'group_allowed' => true, 'participant_list_required' => false, 'cancel_deadline_hours' => 1,
            'allowed_roles' => [Roles::STUDENT, Roles::ADMIN_STAFF, Roles::ACADEMIC], 'sort' => 3,
        ]);
        $study->units()->create(['name_tr' => 'Oda', 'name_en' => 'Room']);
        $study->activities()->create(['name_tr' => 'Grup Çalışması', 'name_en' => 'Group Study', 'units_required' => 1, 'allowed_roles' => $study->allowed_roles]);

        foreach ([$gym, $dance, $study] as $f) {
            foreach (range(1, 7) as $d) {
                $f->hours()->create([
                    'weekday' => $d, 'is_closed' => $d === 7,
                    'opens_at' => $d === 6 ? '10:00' : '08:00', 'closes_at' => $d === 6 ? '18:00' : '22:00',
                ]);
            }
        }

        $ball = Equipment::create(['name_tr' => 'Basketbol topu', 'name_en' => 'Basketball', 'total_stock' => 10]);
        $ball->facilities()->attach($gym);
        $vball = Equipment::create(['name_tr' => 'Voleybol topu', 'name_en' => 'Volleyball', 'total_stock' => 8]);
        $vball->facilities()->attach($gym);
        $mat = Equipment::create(['name_tr' => 'Pilates matı', 'name_en' => 'Pilates mat', 'total_stock' => 15]);
        $mat->facilities()->attach($dance);

        // Sahte LDAP kullanıcılarını içe aktar ve sistem rollerini ver.
        if (config('ldap.driver') === 'fake') {
            $dir = new UserDirectory(app(Directory::class));
            foreach (config('ldap.fake_users') as $u) {
                $dir->findOrImport($u['username']);
            }
            User::where('username', 'antrenor')->update(['is_coach' => true]);
            User::where('username', 'gorevli')->update(['is_attendant' => true]);
            User::where('username', 'sksadmin')->update(['is_sks_admin' => true]);
            $gym->approvers()->attach(User::where('username', 'idari')->value('id'));
            $gym->attendants()->attach(User::where('username', 'gorevli')->value('id'));
            $dance->attendants()->attach(User::where('username', 'gorevli')->value('id'));

            Team::create(['name' => 'Yaşar Erkek Basketbol Takımı', 'sport' => 'Basketbol', 'coach_id' => User::where('username', 'antrenor')->value('id')]);
            Team::create(['name' => 'Yaşar Kadın Voleybol Takımı', 'sport' => 'Voleybol', 'coach_id' => User::where('username', 'antrenor')->value('id')]);
        }

        KvkkText::create([
            'version' => '0.1-taslak',
            'published_at' => now(),
            'body_tr' => "TASLAK — Bu metin Hukuk Birimi tarafından hazırlanacak gerçek aydınlatma metniyle değiştirilecektir.\n\nVeri sorumlusu: Yaşar Üniversitesi.\nİşlenen veriler: ad, soyad, öğrenci/personel numarası, e-posta, bölüm, rezervasyon ve katılım kayıtları; takım oyuncuları için ayrıca iletişim ve sağlık raporu bilgileri.\nAmaç: tesis rezervasyonlarının planlanması, onaylanması ve güvenli kullanımı.\nSaklama süresi: rezervasyon kayıtları 5 yıl; takım oyuncularının sağlık bilgileri takımdan ayrılınca silinir.\nHaklarınız: KVKK md. 11 kapsamındaki haklarınız için SKS Daire Başkanlığı'na başvurabilirsiniz.",
            'body_en' => "DRAFT — This text will be replaced by the official privacy notice prepared by the Legal Office.\n\nData controller: Yaşar University.\nData processed: name, student/staff number, e-mail, department, reservation and attendance records; for team players also contact and medical certificate data.\nPurpose: planning, approval and safe use of facility reservations.\nRetention: reservation records 5 years; players' health data is deleted when they leave the team.\nYour rights: contact the Directorate of Health, Culture and Sports for your rights under KVKK Art. 11.",
        ]);
    }
}
