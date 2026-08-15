<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DhakabypassEmployeeSeeder extends Seeder
{
    /**
     * List of all 28 employees from the authoritative erp.dhakabypass.com registers.
     */
    public static function getEmployeeList(): array
    {
        return [
            ['name' => 'Debashis Jha', 'username' => 'debashis.jha', 'email' => 'debashis.jha@dhakabypass.com', 'employee_id' => 101],
            ['name' => 'Prodip Kumar Saha', 'username' => 'prodip.saha', 'email' => 'prodip.saha@dhakabypass.com', 'employee_id' => 102],
            ['name' => 'Md. Habibur Rahman', 'username' => 'habibur.rahman', 'email' => 'habibur.rahman@dhakabypass.com', 'employee_id' => 103],
            ['name' => 'A. K. M Shifur Rahaman', 'username' => 'shifur.rahaman', 'email' => 'shifur.rahaman@dhakabypass.com', 'employee_id' => 104],
            ['name' => 'MD. Munirujjaman', 'username' => 'munirujjaman', 'email' => 'munirujjaman@dhakabypass.com', 'employee_id' => 105],
            ['name' => 'Md. Fuad Amin', 'username' => 'fuad.amin', 'email' => 'fuad.amin@dhakabypass.com', 'employee_id' => 106],
            ['name' => 'Md. Uzzal Mia', 'username' => 'uzzal.mia', 'email' => 'uzzal.mia@dhakabypass.com', 'employee_id' => 107],
            ['name' => 'Md. Sobuj', 'username' => 'md.sobuj', 'email' => 'sobuj@dhakabypass.com', 'employee_id' => 108],
            ['name' => 'Md. Babar Sardar', 'username' => 'babar.sardar', 'email' => 'babar.sardar@dhakabypass.com', 'employee_id' => 109],
            ['name' => 'Md. Main Uddin Sarker', 'username' => 'main.sarker', 'email' => 'main.sarker@dhakabypass.com', 'employee_id' => 110],
            ['name' => 'Nymul Islam', 'username' => 'nymul.islam', 'email' => 'nymul.islam@dhakabypass.com', 'employee_id' => 111],
            ['name' => 'Md. Emamul Hasan Jasim', 'username' => 'emamul.jasim', 'email' => 'emamul.jasim@dhakabypass.com', 'employee_id' => 112],
            ['name' => 'Subrata Kumar Chaki', 'username' => 'subrata.chaki', 'email' => 'subrata.chaki@dhakabypass.com', 'employee_id' => 113],
            ['name' => 'Md. Raisul Islam Rahat', 'username' => 'raisul.rahat', 'email' => 'raisul.rahat@dhakabypass.com', 'employee_id' => 114],
            ['name' => 'Md. Fahim Hossain', 'username' => 'fahim.hossain', 'email' => 'fahim.hossain@dhakabypass.com', 'employee_id' => 115],
            ['name' => 'Muhammad Rajibul Hoque Molla', 'username' => 'rajibul.molla', 'email' => 'rajibul.molla@dhakabypass.com', 'employee_id' => 116],
            ['name' => 'Md. Nazmul Hasan', 'username' => 'nazmul.hasan', 'email' => 'nazmul.hasan@dhakabypass.com', 'employee_id' => 117],
            ['name' => 'A.S.M Oli Ahammed', 'username' => 'oli.ahammed', 'email' => 'oli.ahammed@dhakabypass.com', 'employee_id' => 118],
            ['name' => 'Md. Mazed Mia', 'username' => 'mazed.mia', 'email' => 'mazed.mia@dhakabypass.com', 'employee_id' => 119],
            ['name' => 'Md. Abdul Hannan', 'username' => 'abdul.hannan', 'email' => 'abdul.hannan@dhakabypass.com', 'email_alt' => 'hannan@dhakabypass.com', 'employee_id' => 120],
            ['name' => 'Md. Shukur Sardar', 'username' => 'shukur.sardar', 'email' => 'shukur.sardar@dhakabypass.com', 'employee_id' => 121],
            ['name' => 'Emam Hosen', 'username' => 'emam.hosen', 'email' => 'emam.hosen@dhakabypass.com', 'employee_id' => 122],
            ['name' => 'Keshab Lal Kundu', 'username' => 'keshab.kundu', 'email' => 'keshab.kundu@dhakabypass.com', 'employee_id' => 123],
            ['name' => 'Md. Ali Amzad', 'username' => 'ali.amzad', 'email' => 'ali.amzad@dhakabypass.com', 'employee_id' => 124],
            ['name' => 'Elias', 'username' => 'elias', 'email' => 'elias@dhakabypass.com', 'employee_id' => 125],
            ['name' => 'Tanvir Mahmud Saif', 'username' => 'tanvir.saif', 'email' => 'tanvir.saif@dhakabypass.com', 'employee_id' => 126],
            ['name' => 'Zidan Ul Azim', 'username' => 'zidan.azim', 'email' => 'zidan.azim@dhakabypass.com', 'employee_id' => 127],
            ['name' => 'Md. Razon Miah', 'username' => 'razon.miah', 'email' => 'razon.miah@dhakabypass.com', 'employee_id' => 128],
        ];
    }

    public function run(): void
    {
        $this->command?->info('🚀 Seeding erp.dhakabypass.com employees list...');

        $employeeRole = Role::firstOrCreate(['name' => 'Employee', 'guard_name' => 'web']);
        $defaultPassword = Hash::make('password123');

        $countCreated = 0;
        $countUpdated = 0;

        foreach (self::getEmployeeList() as $emp) {
            $user = User::where('user_name', $emp['username'])
                ->orWhere('email', $emp['email'])
                ->first();

            if (! $user && isset($emp['email_alt'])) {
                $user = User::where('email', $emp['email_alt'])->first();
            }

            if (! $user) {
                $user = User::create([
                    'name' => $emp['name'],
                    'user_name' => $emp['username'],
                    'email' => $emp['email'],
                    'employee_id' => $emp['employee_id'],
                    'password' => $defaultPassword,
                ]);
                $countCreated++;
            } else {
                $user->update([
                    'name' => $emp['name'],
                    'employee_id' => $user->employee_id ?? $emp['employee_id'],
                ]);
                $countUpdated++;
            }

            if (! $user->hasRole('Employee')) {
                $user->assignRole($employeeRole);
            }
        }

        $this->command?->info("✅ Successfully processed 28 employees ({$countCreated} created, {$countUpdated} updated).");
    }
}
