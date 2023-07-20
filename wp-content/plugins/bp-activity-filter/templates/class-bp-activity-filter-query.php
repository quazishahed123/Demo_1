<?php
/**
 * Defining class for Filtering activity stream.
 *
 * @package BuddyPress_Activity_Filter
 */

if ( ! class_exists( 'WbCom_BP_Activity_Filter_Activity_Stream' ) ) {
	/**
	 * Defining class for Filtering activity stream.
	 *
	 * @package BuddyPress_Activity_Filter
	 */
	class WbCom_BP_Activity_Filter_Activity_Stream {
		/**
		 * Constructor
		 */
		public function __construct() {
			/**
			 * Filtering activity stream
			 */
			add_filter( 'bp_ajax_querystring', array( $this, 'filtering_activity_default' ), 999, 2 );
			add_action( 'wp_enqueue_scripts', array( $this, 'bpaf_enqueue_scripts' ) );
			add_action( 'bp_activity_before_save', array( $this, 'bpaf_activity_do_not_save' ), 5, 1 );
			add_action( 'friends_friendship_accepted', array( $this, 'bpaf_bp_friends_friendship_accepted_activity' ), 5, 4 );

			add_action( 'bp_template_redirect', array( $this, 'bpaf_bp_set_default_activity_filter' ) );
		}

		/**
		 * Registers the script if $src provided (does NOT overwrite), and enqueues it.
		 *
		 * @return void
		 */
		public function bpaf_enqueue_scripts() {

			/**
			 * This function is provided for demonstration purposes only.
			 *
			 * An instance of this class should be passed to the run() function
			 * defined in Bp_Add_Group_Types_Loader as all of the hooks are defined
			 * in that particular class.
			 *
			 * The Bp_Add_Group_Types_Loader will then create the relationship
			 * between the defined hooks and the functions defined in this
			 * class.
			 */
			global $bp;
			if ( bp_is_user_activity() ) {
				$defult_activity_stream = bp_get_option( 'bp-default-profile-filter-name' );
			} else {
				$defult_activity_stream = bp_get_option( 'bp-default-filter-name' );
			}

			if ( empty( $defult_activity_stream ) || $defult_activity_stream == -1 ) {
				$defult_activity_stream = 0;
			}
			wp_enqueue_script( 'wp-embed' );

			wp_enqueue_script( 'bp-activity-filter-public', plugin_dir_url( __FILE__ ) . 'js/buddypress-activity-filter-public.js', array( 'jquery' ), time(), false );

			wp_localize_script(
				'bp-activity-filter-public',
				'bpaf_js_object',
				array(
					'default_filter' => $defult_activity_stream,
				)
			);

		}

		/**
		 * Modifying activity loop for default acitvity.
		 *
		 * @param  string $query (string) Current query string.
		 * @param string $object Current template component.
		 */
		public function filtering_activity_default( $query, $object ) {
			global $bp;
			$query_size = '';
			if ( 'activity' != $object ) {
				return $query;
			}
			$active_plugins = get_option( 'active_plugins' );
			if ( in_array( 'buddypress-hashtag/buddypress-hashtags.php', $active_plugins ) ) {
				return $query;
			}
			$bpaf_filter_nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
			if ( wp_verify_nonce( $bpaf_filter_nonce, '_wpnonce_activity_filter' ) ) {
				return true;
			}

			if ( ! empty( $_POST['cookie'] ) ) {
				$_BP_COOKIE = wp_parse_args( str_replace( '; ', '&', urldecode( sanitize_text_field( wp_unslash( $_POST['cookie'] ) ) ) ) );
			} else {
				$_BP_COOKIE = &$_COOKIE;
			}

			if ( ! empty( $query ) ) {
				$bp_query     = explode( '&', $query );
				$bp_query_arr = $bp_query;
				$page         = array_pop( $bp_query_arr );
				$qs           = explode( '=', $page );
				if ( 'page' == $qs[0] ) {
					$size       = $qs[1];
					$query_size = count( $bp_query );
				}
			} else {
				$bp_query = array();
				$size     = count( $bp_query );
			}

			if ( bp_is_group_activity() ) {
				$defult_activity_stream = -1;
			} elseif ( bp_is_user_activity() ) {
				$defult_activity_stream = bp_get_option( 'bp-default-profile-filter-name' );
			} else {
				$defult_activity_stream = bp_get_option( 'bp-default-filter-name' );
				$page_actions           = bp_activity_get_actions_for_context( 'activity' );
			}

			$hidden_activity_stream = array();
			$hidden_activity_stream = bp_get_option( 'bp-hidden-filters-name' );
			$activity_hidden        = array();
			if ( ! empty( $hidden_activity_stream ) ) {
				$activity_hidden = $hidden_activity_stream;
			}
			if ( ( $defult_activity_stream != -1 ) && ( 1 == $_BP_COOKIE['bpaf-default-filter'] ) ) {
				$query = wp_parse_args( $query, array() );

				$count                = 0;
				$action               = '';
				$admin_setting_object = new WbCom_BP_Activity_Filter_Admin_Setting();
				$labels               = $admin_setting_object->bpaf_get_labels();
				foreach ( $labels as $l_key => $l_value ) {
					if ( ! empty( $l_value ) ) {
						if ( in_array( $l_key, $activity_hidden ) ) {

						} else {
							if ( $count == 0 ) {
								$action .= $l_key;
								$count++;
							} else {
								$action .= ',' . $l_key;
								$count++;
							}
						}
					}
				}
				if ( $defult_activity_stream != -1 ) {
					$query = 'action=' . $defult_activity_stream;
					if ( isset( $_POST['scope'] ) && $_POST['scope'] != '' ) {
						$query .= '&scope=' . sanitize_text_field( wp_unslash( $_POST['scope'] ) );
					}
					if ( ! empty( $page ) ) {
						$query .= '&' . $page;
					}
				} else {
					$query = 'action=' . $action;
				}
			} elseif ( $defult_activity_stream == -1 && ( 1 == $_BP_COOKIE['bpaf-default-filter'] ) || empty( $query ) || ( 1 == $query_size ) ) {
				$count                = 0;
				$action               = '';
				$admin_setting_object = new WbCom_BP_Activity_Filter_Admin_Setting();
				$labels               = $admin_setting_object->bpaf_get_labels();
				if ( ! empty( $labels ) ) {
					foreach ( $labels as $l_key => $l_value ) {
						if ( ! empty( $l_value ) ) {
							if ( ! empty( $hidden_activity_stream ) ) {
								if ( in_array( $l_key, $hidden_activity_stream ) ) {

								} else {
									if ( $count == 0 ) {
										$action .= $l_key;
										$count++;
									} else {
										$action .= ',' . $l_key;
										$count++;
									}
								}
							}
						}
					}
				}
				$query = 'action=' . $action;
				if ( isset( $_POST['scope'] ) && $_POST['scope'] != '' ) {
					$query .= '&scope=' . sanitize_text_field( wp_unslash( $_POST['scope'] ) );
				}
				if ( ! empty( $page ) ) {
					$query .= '&' . $page;
				}
			}
			return $query;
		}

		/**
		 * Restrict to save activity.
		 *
		 * @param object $activity_object Activity Object.
		 */
		public function bpaf_activity_do_not_save( $activity_object ) {
			$hidden_activity_stream = bp_get_option( 'bp-hidden-filters-name' );
			if ( ! empty( $hidden_activity_stream ) && is_array( $hidden_activity_stream ) ) {
				if ( in_array( $activity_object->type, $hidden_activity_stream ) ) {
					$activity_object->type = false;
				}
			}
		}

		/**
		 * Restrict to create friendship activity.
		 *
		 * @param int    $friendship_id ID of the pending friendship object.
		 * @param int    $initiator_user_id ID of the friendship initiator.
		 * @param int    $friend_user_id ID of the user requested friendship with.
		 * @param object $friendship BuddyPress Friendship Object.
		 */
		public function bpaf_bp_friends_friendship_accepted_activity( $friendship_id, $initiator_user_id, $friend_user_id, $friendship = false ) {
			$hidden_activity_stream = bp_get_option( 'bp-hidden-filters-name' );
			if ( ! empty( $hidden_activity_stream ) && is_array( $hidden_activity_stream ) ) {
				if ( in_array( 'friendship_accepted,friendship_created', $hidden_activity_stream ) ) {
					remove_action( 'friends_friendship_accepted', 'bp_friends_friendship_accepted_activity', 10, 4 );
				}
			}
		}

		/**
		 * Fires inside the 'bp_template_redirect' function.
		 *
		 * @since BuddyPress 1.6.0
		 */
		public function bpaf_bp_set_default_activity_filter() {
			// If the filter is already set, do not do anything ok.
			if ( isset( $_COOKIE['bp-activity-filter'] ) ) {
				return;
			}
			// additional check for activity dir and profile activity.
			if ( ! bp_is_activity_directory() && ! bp_is_user_activity() ) {
				return;
			}
			if ( bp_is_user_activity() ) {
				$filter = bp_get_option( 'bp-default-profile-filter-name' );
			} else {
				$filter = bp_get_option( 'bp-default-filter-name' );
			}
			// Set filter to our respective filter.,
			// In this case, I am setting filter to the 'Updates' filter.
			setcookie( 'bp-activity-filter', $filter, null, '/' );
			$_COOKIE['bp-activity-filter'] = $filter;
		}

	}
}
if ( class_exists( 'WbCom_BP_Activity_Filter_Activity_Stream' ) ) {
	$filter_query_obj = new WbCom_BP_Activity_Filter_Activity_Stream();
}
