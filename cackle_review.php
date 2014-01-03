<?php

//Define timer for sync
define('CACKLE_TIMER', 300);

require_once(dirname(__FILE__) . '/cackle_sync.php');


class CackleReview{

    function CackleReview($init=true,$product_id){
        $this->product_id = $product_id;
        global $db;
        if ($init){
            $this->cackle_auth();
            $sync = new CackleReviewSync();
            if ($this->time_is_over(CACKLE_TIMER)){
                $sync->init();
            }
            $this->cackle_display_reviews();

        }
    }
    
    function time_is_over($cron_time){
        $cackle_api = new CackleReviewAPI();
        $get_last_time = $cackle_api->cackle_get_param("last_time");
        $now=time();
        if ($get_last_time==""){
            $set_time = $cackle_api->cackle_set_param("last_time",$now);
            return time();
        }
        else{
            if($get_last_time + $cron_time > $now){
                return false;
            }
            if($get_last_time + $cron_time < $now){
                $set_time = $cackle_api->cackle_set_param("last_time",$now);
                return $cron_time;
            }
        }
    }

    function cackle_auth() {
        $cackle_api = new CackleReviewAPI();
        $siteApiKey = $cackle_api->cackle_get_param("site_api");
        $timestamp = time();
        if ($_SESSION['dle_user_id']) {
            $user_id = $_SESSION['dle_user_id'];
            $user_info = $cackle_api->db_connect("select * from ".PREFIX."_users where user_id = $user_id");
            $user_info = $user_info[0];
            $user = array(
                'id' => $user_id,
                'name' => $user_info["name"],
                'email' => $user_info["email"],
                'avatar' => AVATAR_PATH . $user_info["foto"]
            );
            $user_data = base64_encode(json_encode($user));
        } else {
            $user = '{}';
            $user_data = base64_encode($user);
        }
        $sign = md5($user_data . $siteApiKey . $timestamp);
        return "$user_data $sign $timestamp";
    }


     function cackle_review( $review) {
        
        ?><li  id="cackle-review-<?php echo $review['id']; ?>">
              <div id="cackle-review-header-<?php echo $review['review_id']; ?>" class="cackle-review-header">
                  <cite id="cackle-cite-<?php echo $review['id']; ?>">
                  <?php if($review['autor']) : ?>
                      <a id="cackle-author-user-<?php echo $review['id']; ?>" href="#" target="_blank" rel="nofollow"><?php echo $review['autor']; ?></a>
                  <?php else : ?>
                      <span id="cackle-author-user-<?php echo $review['id']; ?>"><?php echo $review['name']; ?></span>
                  <?php endif; ?>
                  </cite>
              </div>
              <div id="cackle-review-body-<?php echo $review['id']; ?>" class="cackle-review-body">
                  <div id="cackle-review-message-<?php echo $review['id']; ?>" class="cackle-review-message">
                  <?php echo $review['comment']; ?>
                  </div>
                  <div id="cackle-review-message-<?php echo $review['id']; ?>" class="cackle-review-message">
                      <?php echo $review['dignity']; ?>
                  </div>
                  <div id="cackle-review-message-<?php echo $review['id']; ?>" class="cackle-review-message">
                      <?php echo $review['lack']; ?>
                  </div>
              </div>
          </li><?php } 
    
     
     function cackle_display_reviews(){
         global $cackle_api;
         $cackle_api = new CackleReviewAPI();?>
        <div id="mc-review">

                <ul id="cackle-reviews">
                <?php $this->list_reviews(); ?>
                </ul>

        </div>
        <script type="text/javascript">
        <?php $product_id = $this->product_id; ?>
        cackle_widget = window.cackle_widget || [];
        cackle_widget.push({widget: 'Review', id: '<?php echo $cackle_api->cackle_get_param("site_id"); //from cackle's admin panel?>', lang: 'en', channel: '<?php echo($product_id)?>' });
        document.getElementById('mc-review').innerHTML = '';
        (function() {
            var mc = document.createElement("script");
            mc.type = "text/javascript";
            mc.async = true;
            mc.src = ("https:" == document.location.protocol ? "https" : "http") + "://cackle.me/widget.js";
            var s = document.getElementsByTagName("script")[0]; s.parentNode.insertBefore(mc, s.nextSibling);
        })();
        </script>
     <a id="mc-link" href="http://cackle.me">Social reviews <b style="color:#4FA3DA">Cackl</b><b style="color:#F65077">e</b></a>
<?php }
    function get_local_reviews(){
        //getting all reviews for special post_id from database.
        $cackle_api = new CackleReviewAPI();
        $product_id = $this->product_id;
        $get_all_reviews = $cackle_api->db_connect("select * from ".PREFIX."_reviews where product_id = $product_id and approve = 1;");
        return $get_all_reviews;
    }
    function list_reviews(){
        $obj = $this->get_local_reviews();
        if ($obj){
            foreach ($obj as $review) {
                $this->cackle_review($review);
            }
        }
    }
}

?>
