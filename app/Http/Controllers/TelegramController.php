<?php

namespace App\Http\Controllers;

use Telegram\Bot\Laravel\Facades\Telegram;
use Telegram\Bot\Keyboard\Keyboard;
use Telegram\Bot\FileUpload\InputFile;
use Illuminate\Http\Request;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\Log;

class TelegramController extends Controller
{


    protected $step_back = '/start';
    protected $category;

    public function load(Request $request)
    {
        if (isset($request->callback_query)) {
            $this->buttonAction($request);
            return;
        } else {
            Log::debug($request);
            $chat_id = $request->message['from']['id'];
            if (isset($request->message['text'])) {

                switch ($request->message['text']) {

                    case '/start':
                        $this->start($chat_id);
                        break;

                    case 'Категории':
                        $this->categories($chat_id);
                        break;

                    case 'Ещё':
                        $this->getProducts($this->category, $chat_id);
                    default:
                        // Проверяю есть ли такая категория
                        $name = $request->message['text'];
                        $cat = Category::where('rus_name', $name)->first();
                        if (isset($cat)) {
                            $this->getProducts($cat->id,  $chat_id);
                            $this->category = $cat->id;
                        } else {
                            Telegram::sendMessage([
                                'chat_id' =>  $chat_id,
                                'text' => 'Я не умею обрабатывать эту команду'
                            ]);
                        }
                }
                $this->step_back = $request->message['text'];
            } else {
                $this->step_back = '/start';
            }
        }
    }


    private function start($chat_id)
    {
        $keyboard = [
            ['Категории'],
            ['Звонок'],
        ];

        $reply_markup =  Keyboard::make([
            'keyboard' => $keyboard,
            'resize_keyboard' => true,
            'one_time_keyboard' => true
        ]);

        Telegram::sendMessage([
            'chat_id' => $chat_id,
            'text' => 'Выберите действие',
            'reply_markup' => $reply_markup

        ]);
    }


    private function categories($chat_id)
    {
        $category = Category::all('rus_name');
        $keyboard = array();
        foreach ($category as &$value) {
            $keyboard[] = [$value->rus_name];
        }

        $reply_markup =  Keyboard::make([
            'keyboard' => $keyboard,
            'resize_keyboard' => true,
            'one_time_keyboard' => true

        ]);

        Telegram::sendMessage([
            'chat_id' => $chat_id,
            'text' => 'Выберите категорию',
            'reply_markup' =>  $reply_markup

        ]);
    }


    private function getProducts($category, $chat_id)
    {
        $products = Product::where('category_id', $category)->orderBy('price')->paginate(10);
        foreach ($products as &$item) {

            $file = InputFile::create('https://gauss-shop.ru/thumb/2/KvLZtMruC4v28F6NgnYIcg/350r350/d/no-image.jpg', 'test');
            $keyboard = Keyboard::make()
                ->inline()
                ->row(
                    [
                        Keyboard::inlineButton(['text' => 'Btn 2', 'callback_data' => $item->id])
                    ]
                );
            Telegram::sendPhoto([
                'chat_id' => $chat_id,
                'photo' =>  $file,
                'caption' => $item->name,
                'reply_markup' => $keyboard

            ]);
        }
    }



    private function buttonAction(Request $request)
    {
        return;
    }
}
