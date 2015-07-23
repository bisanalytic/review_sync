<h1>This is depricated lib. Please, use <a href="https://bitbucket.org/cackle-plugin/review-php">This</a></h1>
review_sync
===========

Lib for Cackle Review Sync

- Данная библиотека реализует интеграцию Review виджета Cackle в любую php платформу и включает:

- Админ панель - cackle-admin.php  для ввода ключей и запуска полной синхронизации

- Синхронизацию отзывов в локальную БД в течении 5 минут по таймеру

- Вывод html для индексации комментариев


Использование на странице, где будет находится виджет с отзывами:

include_once(dirname(__FILE__) . '/cackle_review.php');
$product_id = 12; //example
$a = new CackleReview(true,$product_id);



