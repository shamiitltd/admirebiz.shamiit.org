<?php

namespace Database\Seeders;

use App\SmSendMessage;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;

class sm_send_messagesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $faker = Faker::create('en_US');
        for ($i = 1; $i <= 5; $i++) {
            $store = new SmSendMessage();
            $store->message_title = $faker->realText($maxNbChars = 30, $indexSize = 2);
            $store->message_des = $faker->realText($maxNbChars = 100, $indexSize = 2);
            $store->notice_date = $faker->dateTime()->format('Y-m-d');
            $store->publish_on = $faker->dateTime()->format('Y-m-d');
            $store->message_to = "2,3,9";
            $store->created_at = date('Y-m-d h:i:s');
            $store->save();
        }
    }
}
