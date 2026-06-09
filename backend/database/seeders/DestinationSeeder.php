<?php

namespace Database\Seeders;

use App\Models\Destination;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DestinationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $destinations = [
            // Gilgit-Baltistan
            ['name' => 'Hunza Valley', 'province' => 'Gilgit-Baltistan', 'region' => 'Northern Areas', 'type' => 'mountains', 'best_season' => 'May to October', 'description' => 'A mountainous valley in the Gilgit-Baltistan region of Pakistan, known for its stunning scenery and hospitable people.', 'latitude' => 36.3167, 'longitude' => 74.6500],
            ['name' => 'Fairy Meadows', 'province' => 'Gilgit-Baltistan', 'region' => 'Northern Areas', 'type' => 'mountains', 'best_season' => 'June to September', 'description' => 'A grassland near one of the base camp sites of the Nanga Parbat, located in Diamer District, Gilgit-Baltistan.', 'latitude' => 35.3855, 'longitude' => 74.5772],
            ['name' => 'Skardu', 'province' => 'Gilgit-Baltistan', 'region' => 'Northern Areas', 'type' => 'mountains', 'best_season' => 'May to September', 'description' => 'A city in Gilgit-Baltistan region of Pakistan, and serves as the capital of Skardu District.', 'latitude' => 35.2971, 'longitude' => 75.6333],
            ['name' => 'Deosai Plains', 'province' => 'Gilgit-Baltistan', 'region' => 'Northern Areas', 'type' => 'mountains', 'best_season' => 'July to September', 'description' => 'The Deosai National Park is a high-altitude alpine plain and national park in northern Pakistan.', 'latitude' => 35.0392, 'longitude' => 75.4601],
            ['name' => 'Attabad Lake', 'province' => 'Gilgit-Baltistan', 'region' => 'Northern Areas', 'type' => 'lakes', 'best_season' => 'April to October', 'description' => 'A lake in Gojal Valley, Hunza, Gilgit Baltistan, Pakistan, created in January 2010 by a landslide dam.', 'latitude' => 36.3312, 'longitude' => 74.8368],
            ['name' => 'Khunjerab Pass', 'province' => 'Gilgit-Baltistan', 'region' => 'Northern Areas', 'type' => 'mountains', 'best_season' => 'May to October', 'description' => 'A high mountain pass in the Karakoram Mountains in a strategic position on the northern border of Pakistan.', 'latitude' => 36.8497, 'longitude' => 75.4191],
            ['name' => 'Rama Meadows', 'province' => 'Gilgit-Baltistan', 'region' => 'Northern Areas', 'type' => 'forests', 'best_season' => 'June to September', 'description' => 'Beautiful lush green meadows surrounded by snow-capped mountains in Astore Valley.', 'latitude' => 35.3524, 'longitude' => 74.8115],
            ['name' => 'Concordia', 'province' => 'Gilgit-Baltistan', 'region' => 'Karakoram Range', 'type' => 'mountains', 'best_season' => 'June to August', 'description' => 'The confluence of the Baltoro Glacier and the Godwin-Austen Glacier, in the heart of the Karakoram range.', 'latitude' => 35.7366, 'longitude' => 76.5165],
            ['name' => 'K2 Base Camp', 'province' => 'Gilgit-Baltistan', 'region' => 'Karakoram Range', 'type' => 'mountains', 'best_season' => 'June to August', 'description' => 'The base camp for the second-highest mountain on Earth.', 'latitude' => 35.8117, 'longitude' => 76.5147],
            ['name' => 'Nanga Parbat Base Camp', 'province' => 'Gilgit-Baltistan', 'region' => 'Himalayas', 'type' => 'mountains', 'best_season' => 'June to September', 'description' => 'The base camp located at the foot of Nanga Parbat, known as the Killer Mountain.', 'latitude' => 35.2372, 'longitude' => 74.5891],

            // KPK
            ['name' => 'Swat Valley', 'province' => 'KPK', 'region' => 'Northern Areas', 'type' => 'mountains', 'best_season' => 'April to October', 'description' => 'A river valley and administrative district in the Khyber Pakhtunkhwa province of Pakistan.', 'latitude' => 35.2227, 'longitude' => 72.4258],
            ['name' => 'Naran-Kaghan', 'province' => 'KPK', 'region' => 'Northern Areas', 'type' => 'mountains', 'best_season' => 'May to September', 'description' => 'Famous tourist destinations known for their lakes and rivers.', 'latitude' => 34.9080, 'longitude' => 73.6517],
            ['name' => 'Chitral', 'province' => 'KPK', 'region' => 'Northern Areas', 'type' => 'mountains', 'best_season' => 'May to October', 'description' => 'A town situated in the Chitral District of Khyber Pakhtunkhwa.', 'latitude' => 35.8510, 'longitude' => 71.7864],
            ['name' => 'Kalash Valley', 'province' => 'KPK', 'region' => 'Northern Areas', 'type' => 'cultural', 'best_season' => 'May to October', 'description' => 'Valleys in Chitral District, surrounded by the Hindu Kush mountain range.', 'latitude' => 35.7259, 'longitude' => 71.7451],
            ['name' => 'Shandur Pass', 'province' => 'KPK', 'region' => 'Northern Areas', 'type' => 'mountains', 'best_season' => 'June to September', 'description' => 'Known as the Roof of the World, connects Chitral and Gilgit-Baltistan.', 'latitude' => 36.0827, 'longitude' => 72.5447],
            ['name' => 'Kalam', 'province' => 'KPK', 'region' => 'Northern Areas', 'type' => 'mountains', 'best_season' => 'May to September', 'description' => 'A valley located at a distance of 99 kilometers from Mingora in the northern upper reaches of Swat valley.', 'latitude' => 35.4855, 'longitude' => 72.5852],
            ['name' => 'Kumrat Valley', 'province' => 'KPK', 'region' => 'Northern Areas', 'type' => 'forests', 'best_season' => 'May to October', 'description' => 'A valley in the Upper Dir District of Khyber Pakhtunkhwa province of Pakistan.', 'latitude' => 35.5367, 'longitude' => 72.2235],
            ['name' => 'Malam Jabba', 'province' => 'KPK', 'region' => 'Northern Areas', 'type' => 'mountains', 'best_season' => 'December to March', 'description' => 'A Hill Station in the Hindu Kush mountain range nearly 40 kilometers from Saidu Sharif in Swat Valley.', 'latitude' => 34.7981, 'longitude' => 72.5714],
            ['name' => 'Dir', 'province' => 'KPK', 'region' => 'Northern Areas', 'type' => 'historical', 'best_season' => 'April to October', 'description' => 'A district in the Khyber Pakhtunkhwa province of Pakistan.', 'latitude' => 35.2074, 'longitude' => 71.8768],
            ['name' => 'Shogran', 'province' => 'KPK', 'region' => 'Northern Areas', 'type' => 'mountains', 'best_season' => 'May to September', 'description' => 'A hill station situated on a green plateau in the Kaghan Valley.', 'latitude' => 34.6309, 'longitude' => 73.4687],

            // AJK
            ['name' => 'Neelum Valley', 'province' => 'AJK', 'region' => 'Kashmir', 'type' => 'mountains', 'best_season' => 'May to October', 'description' => 'A 144 km long bow-shaped thick forested region in Azad Kashmir.', 'latitude' => 34.5828, 'longitude' => 73.9048],
            ['name' => 'Keran', 'province' => 'AJK', 'region' => 'Kashmir', 'type' => 'mountains', 'best_season' => 'May to October', 'description' => 'A village and tourist resort in Neelum Valley, Azad Kashmir.', 'latitude' => 34.6310, 'longitude' => 73.9482],
            ['name' => 'Ratti Gali Lake', 'province' => 'AJK', 'region' => 'Kashmir', 'type' => 'lakes', 'best_season' => 'June to September', 'description' => 'An alpine glacial lake which is located in Neelum Valley, Azad Kashmir.', 'latitude' => 34.8256, 'longitude' => 74.0535],
            ['name' => 'Taobat', 'province' => 'AJK', 'region' => 'Kashmir', 'type' => 'mountains', 'best_season' => 'May to September', 'description' => 'The last station in Neelum valley, located at a distance of 200 kilometers from Muzaffarabad.', 'latitude' => 34.8711, 'longitude' => 74.7082],
            ['name' => 'Banjosa Lake', 'province' => 'AJK', 'region' => 'Kashmir', 'type' => 'lakes', 'best_season' => 'All year', 'description' => 'An artificial lake and a tourist resort 18 kilometers from the city of Rawalakot.', 'latitude' => 33.8055, 'longitude' => 73.8188],

            // Punjab
            ['name' => 'Lahore', 'province' => 'Punjab', 'region' => 'Central', 'type' => 'historical', 'best_season' => 'October to March', 'description' => 'The capital city of the Pakistani province of Punjab and the second largest city in Pakistan.', 'latitude' => 31.5204, 'longitude' => 74.3587],
            ['name' => 'Murree', 'province' => 'Punjab', 'region' => 'Northern Areas', 'type' => 'mountains', 'best_season' => 'All year', 'description' => 'A mountain resort town, located in the Galyat region of the Pir Panjal Range.', 'latitude' => 33.9070, 'longitude' => 73.3943],
            ['name' => 'Rohtas Fort', 'province' => 'Punjab', 'region' => 'Potohar', 'type' => 'historical', 'best_season' => 'October to March', 'description' => 'A 16th-century fortress located near the city of Jhelum.', 'latitude' => 32.9725, 'longitude' => 73.5786],
            ['name' => 'Cholistan Desert', 'province' => 'Punjab', 'region' => 'Southern', 'type' => 'desert', 'best_season' => 'November to February', 'description' => 'A desert spanning thirty kilometers from Bahawalpur.', 'latitude' => 28.5306, 'longitude' => 71.0505],
            ['name' => 'Bahawalpur', 'province' => 'Punjab', 'region' => 'Southern', 'type' => 'historical', 'best_season' => 'October to March', 'description' => 'Known for its palaces and architectural monuments.', 'latitude' => 29.3957, 'longitude' => 71.6833],
            ['name' => 'Multan', 'province' => 'Punjab', 'region' => 'Southern', 'type' => 'historical', 'best_season' => 'October to March', 'description' => 'Known as the City of Saints due to the large number of shrines and Sufi saints.', 'latitude' => 30.1575, 'longitude' => 71.5249],
            ['name' => 'Taxila', 'province' => 'Punjab', 'region' => 'Potohar', 'type' => 'historical', 'best_season' => 'October to March', 'description' => 'An important archaeological site of an ancient Indian city.', 'latitude' => 33.7463, 'longitude' => 72.8397],

            // Sindh
            ['name' => 'Karachi', 'province' => 'Sindh', 'region' => 'Coastal', 'type' => 'beach', 'best_season' => 'November to February', 'description' => 'The largest city in Pakistan and the twelfth largest city in the world.', 'latitude' => 24.8607, 'longitude' => 67.0011],
            ['name' => 'Mohenjo-daro', 'province' => 'Sindh', 'region' => 'Interior', 'type' => 'historical', 'best_season' => 'November to February', 'description' => 'An archaeological site in the province of Sindh, built around 2500 BCE.', 'latitude' => 27.3292, 'longitude' => 68.1388],
            ['name' => 'Makli Necropolis', 'province' => 'Sindh', 'region' => 'Coastal', 'type' => 'historical', 'best_season' => 'November to February', 'description' => 'One of the largest funerary sites in the world, spread over an area of 10 square kilometers.', 'latitude' => 24.7570, 'longitude' => 67.8967],
            ['name' => 'Keenjhar Lake', 'province' => 'Sindh', 'region' => 'Coastal', 'type' => 'lakes', 'best_season' => 'November to February', 'description' => 'The second largest fresh water lake in Pakistan, situated in Thatta District.', 'latitude' => 24.9667, 'longitude' => 68.0433],
            ['name' => 'Tharparkar Desert', 'province' => 'Sindh', 'region' => 'Interior', 'type' => 'desert', 'best_season' => 'November to February', 'description' => 'The only fertile desert in the world, located in Sindh.', 'latitude' => 24.8828, 'longitude' => 70.3662],
            ['name' => 'Kirthar National Park', 'province' => 'Sindh', 'region' => 'Interior', 'type' => 'desert', 'best_season' => 'November to February', 'description' => 'Second largest national park in Pakistan.', 'latitude' => 25.7533, 'longitude' => 67.6582],

            // Balochistan
            ['name' => 'Quetta', 'province' => 'Balochistan', 'region' => 'Northern', 'type' => 'mountains', 'best_season' => 'April to October', 'description' => 'The provincial capital and largest city of Balochistan, Pakistan.', 'latitude' => 30.1798, 'longitude' => 66.0245],
            ['name' => 'Gwadar', 'province' => 'Balochistan', 'region' => 'Coastal', 'type' => 'beach', 'best_season' => 'October to March', 'description' => 'A port city on the southwestern coast of Balochistan.', 'latitude' => 25.1216, 'longitude' => 62.3254],
            ['name' => 'Hingol National Park', 'province' => 'Balochistan', 'region' => 'Coastal', 'type' => 'beach', 'best_season' => 'October to March', 'description' => 'The largest national park in Pakistan, located along the Makran coast.', 'latitude' => 25.5085, 'longitude' => 65.5186],
            ['name' => 'Ziarat', 'province' => 'Balochistan', 'region' => 'Northern', 'type' => 'forests', 'best_season' => 'May to September', 'description' => 'A city in the Ziarat District of Balochistan, known for its juniper forests.', 'latitude' => 30.3807, 'longitude' => 67.7288],
            ['name' => 'Kund Malir Beach', 'province' => 'Balochistan', 'region' => 'Coastal', 'type' => 'beach', 'best_season' => 'October to March', 'description' => 'A desert beach in Hingol National Park.', 'latitude' => 25.3995, 'longitude' => 65.4542],
            ['name' => 'Ormara', 'province' => 'Balochistan', 'region' => 'Coastal', 'type' => 'beach', 'best_season' => 'October to March', 'description' => 'A town in Gwadar District in the Balochistan province of Pakistan.', 'latitude' => 25.2088, 'longitude' => 64.6357],
            ['name' => 'Hanna Lake', 'province' => 'Balochistan', 'region' => 'Northern', 'type' => 'lakes', 'best_season' => 'April to October', 'description' => 'A lake located near Quetta, a popular tourist destination.', 'latitude' => 30.2526, 'longitude' => 66.0963],

            // Islamabad
            ['name' => 'Islamabad', 'province' => 'Islamabad', 'region' => 'Capital', 'type' => 'historical', 'best_season' => 'October to March', 'description' => 'The capital city of Pakistan, known for its greenery and modern architecture.', 'latitude' => 33.6844, 'longitude' => 73.0479],
            ['name' => 'Rawal Lake', 'province' => 'Islamabad', 'region' => 'Capital', 'type' => 'lakes', 'best_season' => 'All year', 'description' => 'An artificial reservoir that provides the water needs for the cities of Rawalpindi and Islamabad.', 'latitude' => 33.7208, 'longitude' => 73.1114],
            ['name' => 'Margalla Hills', 'province' => 'Islamabad', 'region' => 'Capital', 'type' => 'mountains', 'best_season' => 'All year', 'description' => 'A hill range within the Margalla Hills National Park gracefully adjoining Islamabad.', 'latitude' => 33.7439, 'longitude' => 73.0232],
            ['name' => 'Daman-e-Koh', 'province' => 'Islamabad', 'region' => 'Capital', 'type' => 'mountains', 'best_season' => 'All year', 'description' => 'A viewing point and hill top garden north of Islamabad and located in the middle of the Margalla Hills.', 'latitude' => 33.7381, 'longitude' => 73.0560],
        ];

        foreach ($destinations as $dest) {
            $dest['slug'] = Str::slug($dest['name']);
            $dest['is_featured'] = true; // Making them featured just for initial testing
            Destination::firstOrCreate(['slug' => $dest['slug']], $dest);
        }
    }
}
