<?php
require_once(dirname(__FILE__) . '/cackle_api.php');

class CackleReviewSync {
    function CackleReviewSync() {
        $cackle_api = new CackleReviewAPI();
        $this->siteId = $cackle_api->cackle_get_param("site_id");
        $this->accountApiKey = $cackle_api->cackle_get_param("account_api");
        $this->siteApiKey = $cackle_api->cackle_get_param("site_api");
    }

    function init($a = "") {
        $apix = new CackleReviewAPI();
        $review_last_modified = $apix->cackle_get_param("review_last_modified");

        if ($a == "all_reviews") {
            $response1 = $this->get_reviews(0);
        }
        else {
            $response1 = $this->get_reviews($review_last_modified);
        }
        //get reviews from CackleReview Api for sync
        if ($response1==NULL){
            return false;
        }
        $response_size = $this->push_reviews($response1); // get review from array and insert it to wp db
        $totalPages = $this->cackle_json_decodes($response1);
        $totalPages = $totalPages['reviews']['totalPages'];
        if ($totalPages > 1) {

            for ($i=1; $i < $totalPages; $i++ ){

                if ($a=="all_reviews"){
                    $response2 = $this->get_reviews(0,$i) ;
                }
                else{

                    $response2 = $this->get_reviews($review_last_modified,$i) ;
                }
                //$response2 = $apix->get_reviews(($a=="all_reviews") ? 0 : cackle_get_param("cackle_review_last_modified",0),$i);
                //get reviews from CackleReview Api for sync
                $response_size = $this->push_reviews($response2); // get review from array and insert it to wp db
            }
        }
        return "success";
    }

    function get_reviews($review_last_modified, $cackle_page = 0){
        $this->get_url = "http://cackle.me/api/2.0/review/list.json?id=$this->siteId&accountApiKey=$this->accountApiKey&siteApiKey=$this->siteApiKey";
        $host = $this->get_url . "&modified=" . $review_last_modified . "&page=" . $cackle_page . "&size=2";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $host);

        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.6) Gecko/20070725 Firefox/2.0.0.6");
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_ENCODING, "gzip, deflate");
        //curl_setopt($ch,CURLOPT_ENCODING, '');
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-type: application/x-www-form-urlencoded; charset=utf-8',


            )
        );
        $result = curl_exec($ch);
        curl_close($ch);

        return $result;

    }

    function to_i($number_to_format){
        return number_format($number_to_format, 0, '', '');
    }


    function cackle_json_decodes($response){

        $obj = json_decode($response,true);

        return $obj;
    }

    function filter_cp1251($string1){
        $cackle_api = new CackleReviewAPI();
        if ($cackle_api->cackle_get_param("cackle_encoding") == "1"){
            $string2 = iconv("utf-8", "CP1251",$string1);
            //print "###33";
        }
        return $string2;
    }
	function startsWith($haystack, $needle) {
        $length = strlen($needle);
        return (substr($haystack, 0, $length) === $needle);
    }
    function insert_review($review,$status){

        /*
         * Here you can convert $url to your post ID
         */
		if ($this->startsWith($review['channel'], 'http')) {
            $url = 0;
        } else {
            $url = $review['channel'];
        }

        if ($review['user']!=null){
            $author_name = ($review['user']['name']) ? $review['user']['name'] : "";
            $author_www = $review['user']['www'];
            $author_avatar = $review['user']['avatar'];
            $author_provider = $review['user']['provider'];
            $author_anonym_name = "";
            $anonym_email = "";
        }
        else{
            $author_name = ($review['anonym']['name']) ? $review['anonym']['name']: "" ;
            $author_email= ($review['anonym']['email']) ?  $review['anonym']['email'] : "";
            $author_www = "";
            $author_avatar = "";
            $author_provider = "";
            $author_anonym_name = $review['anonym']['name'];
            $anonym_email = $review['anonym']['email'];

        }
        $get_parent_local_id = null;
        $review_id = $review['id'];
        $review_modified = $review['modified'];
        $cackle_api = new CackleReviewAPI();
        if ($cackle_api->cackle_get_param("last_review")==0){
            $cackle_api->cackle_db_prepare();
        }
        $date =strftime("%Y-%m-%d %H:%M:%S", $review['created']/1000);
        $ip = ($review['ip']) ? $review['ip'] : "";
        $comment = $review['comment'];
        $dignity = $review['dignity'];
        $lack = $review['lack'];
        $stars = $review['stars'];
        $rating = $review['rating'];
        $user_agent = 'CackleReview:' . $review['id'];

        $reviewdata = array(
            'post_id' => $url,
            'autor' =>  $author_name,
            'email' =>  $author_email,
            'date' => strftime("%Y-%m-%d %H:%M:%S", $review['created']/1000),
            'ip' => ($review['ip']) ? $review['ip'] : "",
            'text' =>$review['message'],
            'approve' => $status,
            'user_agent' => 'CackleReview:' . $review['id'],


        );
        $conn = $cackle_api->conn();
        if ($cackle_api->cackle_get_param("cackle_encoding") == 1){

            $conn->exec('SET NAMES cp1251');
        }
		else{
		$conn->exec('SET NAMES utf8');
		}

        $sql = "insert into " . PREFIX ."_reviews (product_id,autor,email,avatar,date,ip,comment,dignity,lack,approve,stars,rating,user_agent) values (:product_id, :author_name, :author_email, :author_avatar, :date, :ip, :comment, :dignity, :lack, :status, :stars, :rating, :user_agent ) ";
	    $q = $conn->prepare($sql);
	    $q->execute(
                array(
                    ':product_id'=>($cackle_api->cackle_get_param("cackle_encoding") == 1) ? iconv("utf-8", "CP1251",$url) : $url,
                    ':author_name'=>($cackle_api->cackle_get_param("cackle_encoding") == 1) ? iconv("utf-8", "CP1251",$author_name) : $author_name,
                    ':author_email'=>($cackle_api->cackle_get_param("cackle_encoding") == 1) ? iconv("utf-8", "CP1251",$author_email) : $author_email ,
                    ':author_avatar'=>($cackle_api->cackle_get_param("cackle_encoding") == 1) ? iconv("utf-8", "CP1251",$author_avatar) : $author_avatar ,
                    ':date'=>$date,
                    ':ip'=>$ip,
                    ':comment'=>($cackle_api->cackle_get_param("cackle_encoding") == 1) ? iconv("utf-8", "CP1251",$comment) : $comment,
                    ':dignity'=>($cackle_api->cackle_get_param("cackle_encoding") == 1) ? iconv("utf-8", "CP1251",$dignity) : $dignity,
                    ':lack'=>($cackle_api->cackle_get_param("cackle_encoding") == 1) ? iconv("utf-8", "CP1251",$lack) : $lack,
                    ':status'=>$status,
                    ':stars'=>$stars,
                    ':rating'=>$rating,
                    ':user_agent'=>$user_agent,


                ));
        $q=null;

        $cackle_api->cackle_set_param("last_review",$review_id);
        $get_last_modified = $cackle_api->cackle_get_param("review_last_modified");
        $get_last_modified = (int)$get_last_modified;
        if ($review['modified'] > $get_last_modified) {
            $cackle_api->cackle_set_param("review_last_modified",(string)$review['modified']);
        }

    }

    function review_status_decoder($review) {
        $status;
        if (strtolower($review['status']) == "approved") {
            $status = 1;
        }
        elseif (strtolower($review['status'] == "pending") || strtolower($review['status']) == "rejected") {
            $status = 0;
        }
        elseif (strtolower($review['status']) == "spam") {
            $status = 0;
        }
        elseif (strtolower($review['status']) == "deleted") {
            $status = 0;
        }
        return $status;
    }

    function update_review_status($review_id, $status, $modified, $review_content, $review_rating) {
        $apix = new CackleReviewAPI();
        $cackle_api = new CackleReviewAPI();
        $sql = "update ". PREFIX ."_reviews set approve = ? , comment = ? , rating = ? where user_agent = ?";
        $conn = $cackle_api->conn();
		if ($cackle_api->cackle_get_param("cackle_encoding") == 1){

            $conn->exec('SET NAMES cp1251');
        }
		else{
		    $conn->exec('SET NAMES utf8');
		}
        $q = $conn->prepare($sql);
        $q->execute(array($status,$review_content,$review_rating,"CackleReview:$review_id"));
        $q = null;
        if ($modified > $apix->cackle_get_param('review_last_modified', 0)) {
            $cackle_api->cackle_set_param("review_last_modified",$modified);
        }

    }

    function push_reviews ($response){
        $apix = new CackleReviewAPI();
        $obj = $this->cackle_json_decodes($response,true);
        $obj = $obj['reviews']['content'];
        if ($obj) {
            $reviews_size = count($obj);
            if ($reviews_size != 0){
                foreach ($obj as $review) {
                    if ($review['id'] > $apix->cackle_get_param('last_review')) {
                        $this->insert_review($review, $this->review_status_decoder($review));
                    } else {
                        // if ($review['modified'] > $apix->cackle_get_param('cackle_review_last_modified', 0)) {
                        $this->update_review_status($review['id'], $this->review_status_decoder($review), $review['modified'], $review['comment'], $review['rating'] );
                        // }
                    }
                }
            }
        }
        return $reviews_size;

    }

}
?>