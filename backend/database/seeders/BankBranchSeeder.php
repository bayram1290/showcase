<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Faker\Factory;

use App\Models\BankBranch;
use App\Models\User;

class BankBranchSeeder extends Seeder
{
    /**
     * Seed the application's database with bank branches and assign them to users.
     *
     * Insert bank branches into the 'bank_branches' table and assigns them to users in the 'users' table.
     * Then, first create a central headquarters bank branch with a fixed set of attributes.
     * After that, create bank branches for each district with randomly generated attributes.
     * Finally, assign a bank branch to each user by randomly selecting from the created bank branches.
     *
     * @return void
     */
    public function run(): void
    {
        $fakery = Factory::create();

        $districts = [
            ['name' => 'Ashgabat', 'code' => 'AG', 'phone_code' => '12'],
            ['name' => 'Ahal', 'code' => 'AH', 'phone_code' => '39'],
            ['name' => 'Balkan', 'code' => 'BL', 'phone_code' => '24'],
            ['name' => 'Mary', 'code' => 'MR', 'phone_code' => '55'],
            ['name' => 'Lebap', 'code' => 'LB', '44', 'phone_code' => '44'],
            ['name' => 'Dashoguz', 'code' => 'DZ', 'phone_code' => '34'],
            ['name' => 'Arkadag', 'code' => 'AK', 'phone_code' => '12'],
        ];

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('bank_branches')->insert([
            'id' => 0,
            'name' => 'Central Headquarter',
            'code' => 'HQ',
            'address' => 'Main Street, Capital City',
            'district' => 'Ashgabat',
            'phones' => '+993 123-456-7890/+993 123-654-3210/+993 123-987-6543',
            'fax' => '+993 12-456-7891',
            'is_headquarters' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        foreach ($districts as $district) {
            $combined_fax_phone = "+993 {$district['phone_code']} {$fakery->phoneNumber()}";
            BankBranch::create([
                'name' => $district['name'] . ' Branch ' . $fakery->city(),
                'code' => $district['code'] . '001',
                'address' => $fakery->streetAddress(),
                'district' => $district['name'],
                'is_headquarters' => false,
                'is_active' => true,
                'phones' => "+993 {$district['phone_code']} {$fakery->phoneNumber()}/+993 {$district['phone_code']} {$fakery->phoneNumber()}/{$combined_fax_phone}",
                'fax' => $combined_fax_phone,
            ]);
        }

        $branches = BankBranch::where('id', '!=', 1)->pluck('id')->all();

        User::cursor()->each(function (User $user) use ($branches, $fakery) {
            $user->branch_id = in_array($user->id, [1, 2, 7], true)
                ? 1
                : $fakery->randomElement($branches);
            $user->save();
        });
    }
}
