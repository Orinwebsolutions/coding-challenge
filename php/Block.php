<?php

/**
 * Block class.
 *
 * @package SiteCounts
 */

namespace XWP\SiteCounts;

use WP_Block;
use WP_Query;

/**
 * The Site Counts dynamic block.
 *
 * Registers and renders the dynamic block.
 */
class Block
{

	/**
	 * The Plugin instance.
	 *
	 * @var Plugin
	 */
	protected $plugin;

	/**
	 * Instantiates the class.
	 *
	 * @param Plugin $plugin The plugin object.
	 */
	public function __construct($plugin)
	{
		$this->plugin = $plugin;
	}

	/**
	 * Adds the action to register the block.
	 *
	 * @return void
	 */
	public function init()
	{
		add_action('init', [$this, 'register_block']);
	}

	/**
	 * Registers the block.
	 */
	public function register_block()
	{
		register_block_type_from_metadata(
			$this->plugin->dir(),
			[
				'render_callback' => [$this, 'render_callback'],
			]
		);
	}

	/**
	 * Renders the block.
	 *
	 * @param array    $attributes The attributes for the block.
	 * @param string   $content    The block content, if any.
	 * @param WP_Block $block      The instance of this block.
	 * @return string The markup of the block.
	 */
	public function render_callback($attributes, $content, $block)
	{
		$post_types = get_post_types(['public' => true]);
		$cached_post_count = get_transient('xwp_posts_array');
		if (false === $cached_post_count) :
			$post_count_pre_array = [];
			foreach ($post_types as $post_type_slug) :
				$post_type_object = get_post_type_object($post_type_slug);
				$post_count = count(get_posts(['post_type' => $post_type_slug, 'posts_per_page' => -1]));
				$post_type = $post_type_object->labels->name;
				$post_count_pre_array[] = [$post_count, $post_type];
			endforeach;
			set_transient('xwp_posts_array', $post_count_pre_array, 300);
			$cached_post_count = $post_count_pre_array;
		endif;
		$class_name = isset($attributes['className']);
		ob_start();

?>
		<div class="<?php echo esc_attr($class_name); ?>">
			<h2>Post Counts</h2>
			<ul>
				<?php foreach ($cached_post_count as $post_count) : ?>
					<li><?php echo 'There are ' . esc_html($post_count[0]) . ' ' . esc_html($post_count[1]) . '.'; ?></li>
				<?php endforeach; ?>
			</ul>
			<p>
				<?php
				if (isset($_GET['post_id']) && isset($_GET['post_id_nonce']) && wp_verify_nonce(sanitize_key($_GET['post_id_nonce']), 'post_id_action')) {
					echo 'The current post ID is ' . intval($_GET['post_id']) . '.';
				}
				?>
			</p>

			<?php
			$taxonomy_query = get_transient('xwp_taxonomy');
			if ($taxonomy_query === false) {
				$taxonomy_query = new WP_Query(array(
					'post_type' => ['post', 'page'],
					'post_status' => 'publish',
					'date_query' => array(
						array(
							'hour'      => 9,
							'compare'   => '>=',
						),
						array(
							'hour' => 17,
							'compare' => '<=',
						),
					),
					'tag'  => 'foo',
					'category_name'  => 'baz',
					'post__not_in' => [get_the_ID()],
				));

				set_transient('xwp_taxonomy', $taxonomy_query, 300);
			}
			?>
			<?php if ($taxonomy_query && $taxonomy_query->found_posts) : ?>
				<h2>5 posts between 9AM to 5PM with the tag of foo and the category of baz</h2>
				<ul>
					<?php
					foreach (array_slice($taxonomy_query->posts, 0, 5) as $post) :
					?>
						<li><?php echo esc_html($post->post_title) ?></li>
					<?php
					endforeach;
					?>
				</ul>
			<?php endif; ?>
		</div>
<?php

		return ob_get_clean();
	}
}
