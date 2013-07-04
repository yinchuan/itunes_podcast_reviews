<?php
try {
/**
 * @package iTunes Podcast Reviews
 * @version 0.1
 */
/*
Plugin Name: iTunes Podcast Reviews
Plugin URI: http://
Description: Pull customer reviews for a podcast from iTunes and display as a widget.
Author: yinchuan
Version: 0.1
Author URI: http://www.chuan-small.me
*/

// helper functions

// pull html from iTunes
// return html string
function get_html($podcast_id) {
    //$podcast_id = "535367738";
    $store_front = "143465-2,12";
    $itunes_url = "/WebObjects/MZStore.woa/wa/customerReviews?sort=4&displayable-kind=4&id=".$podcast_id;
    $host = "itunes.apple.com";
    $user_agent = "iTunes/11.0.2 (Windows; Microsoft Windows 7 Ultimate Edition Service Pack 1 (Build 7601)) AppleWebKit/536.27.1";
    $headers = array(
                'User-Agent: '.$user_agent,
                'X-Apple-Store-Front: '.$store_front,
                'X-Apple-Tz: -18000',
                'Accept-Language: en-us, en;q=0.50',
    );

    $curl_handler = curl_init();
    curl_setopt($curl_handler, CURLOPT_URL, $host.$itunes_url);
    curl_setopt($curl_handler, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl_handler, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($curl_handler, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($curl_handler, CURLOPT_FOLLOWLOCATION, true);
    $body = curl_exec($curl_handler);
    curl_close($curl_handler);

    return $body;
}
//// parse reviews from html string
//// this function use simple_html_dom()
function parse_review($body) {
    include 'simple_html_dom.php';
    $html = new simple_html_dom();
    $html -> load($body);
    // get rating count
    $rating_string = $html -> find('.rating-count', 0) -> plaintext;
    $rating_count_array = explode(" ", $rating_string);
    $rating_count = $rating_count_array[0];
    $count = 1;
    $reviews = array();
    $reviews[0] = $rating_count;
    foreach ( $html -> find('.customer-review') as $review ) {
        $reviews[$count] = array();
        foreach( $review -> find('h5 span.customerReviewTitle') as $a_review) {
            $reviews[$count]['title'] = $a_review -> plaintext;
        }
        foreach( $review -> find('span.user-info') as $a_review){
            $reviews[$count]['user-data'] = $a_review -> plaintext;
        }
        foreach( $review -> find('p.content') as $a_review){
            $reviews[$count]['content'] = $a_review -> plaintext;
        }
        $count++;
    }
    $html -> clear();
    //return $rating_count;
    return $reviews;
}
function from_file($filename){
	return unserialize( file_get_contents($filename) );
}
function to_file($data, $filename){
	//fwrite( fopen( tempnam('/tmp', $filename), 'w'), serialize($data) );
	fwrite( fopen( $filename, 'w'), serialize($data) );
}
function get_rating_count($podcast_id, $filename , $refresh_interval) {
	//默认刷新间隔600s
	// 临时过小的话，可以是上一次更新时出错，所以要即使未超时也要重新获取
	if  (! file_exists($filename) or (time() -  filemtime($filename) > $refresh_interval) or filesize($filename) < 100){
		to_file( parse_review( get_html($podcast_id) ), $filename );
	}
	$reviews = from_file($filename);
	return $reviews[0];
}
class iTunes_Podcast_Reviews extends WP_Widget {
	public function __construct() {
		parent::__construct(
	 		'itunes_podcast_reviews', // 基本 ID
			'iTunes Podcast Reviews', // 名称
			array( 'description' => __( '显示来自iTunes上的评论总数', 'text_domain' ), ) // Args
		);
	}
 
	public function widget( $args, $instance ) {
		$TEMP_FILE = '/tmp/itunes_info.txt';
		extract( $args );
		$title = apply_filters( 'widget_title', $instance['title'] );
		$podcast_id = $instance['podcast_id'];
		$refresh_interval = (int ) $new_instance['refresh_interval'];
 
		echo $before_widget;
		if ( ! empty( $title ) )
			echo $before_title . $title . $after_title;
		//echo __( "$podcast_id", 'text_domain' );
		$review_count = get_rating_count($podcast_id, $TEMP_FILE, $refresh_interval);
		echo __( "$review_count", 'text_domain' );
		echo $after_widget;
	}
 
	public function update( $new_instance, $old_instance ) {
		$instance = array();
		$instance['title'] = strip_tags( $new_instance['title'] );
		$instance['podcast_id'] = strip_tags( $new_instance['podcast_id']);
		$instance['show_review_content'] = (bool) $new_instance['show_review_content'];
		$instance['refresh_interval'] = (int ) $new_instance['refresh_interval'];
 
		return $instance;
	}
 
	public function form( $instance ) {
		$title = isset( $instance[ 'title' ] ) ? $instance[ 'title' ] : __( '来自iTunes的评论', 'text_domain' );
		$podcast_id = isset( $instance[ 'podcast_id' ] ) ? $instance[ 'podcast_id' ] : __( '535367738', 'text_domain' );
		$show_review_content = isset( $instance[ 'show_review_content' ] ) ? (bool) $instance[ 'show_review_content' ] : False;
		$refresh_interval = isset( $instance[ 'refresh_interval' ] ) ? (int) $instance[ 'refresh_interval' ] : 600;
		?>

		<p> <label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:' ); ?></label> 
			<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" /> </p>
		<p> <label for="<?php echo $this->get_field_id( 'podcast_id' ); ?>"><?php _e( 'Podcast ID:' ); ?></label> 
			<input class="widefat" id="<?php echo $this->get_field_id( 'podcast_id' ); ?>" name="<?php echo $this->get_field_name( 'podcast_id' ); ?>" type="text" value="<?php echo esc_attr( $podcast_id ); ?>" /> </p>
		<p> <label for="<?php echo $this->get_field_id( 'refresh_interval' ); ?>"><?php _e( '刷新间隔时间(s):' ); ?></label> 
			<input class="widefat" id="<?php echo $this->get_field_id( 'refresh_interval' ); ?>" name="<?php echo $this->get_field_name( 'refresh_interval' ); ?>" type="text" value="<?php echo esc_attr( $refresh_interval ); ?>" /> </p>
		<!-- <p>
			<input class="checkbox" type="checkbox" <?php checked( $show_review_content ); ?> id="<?php echo $this->get_field_id( 'show_review_content' ); ?>" name="<?php echo $this->get_field_name( 'show_review_content' ); ?>" />
                	<label for="<?php echo $this->get_field_id( 'show_review_content' ); ?>"><?php _e( '显示评论内容?' ); ?></label>
		</p> -->

		<?php 
	}
 
}

add_action('widgets_init', function(){
	register_widget('iTunes_Podcast_Reviews');
});

} catch (Exception $e) {
	echo "error in iTunes plunin!";
}
?>
