<?php
/**
 * SEO, social tags, structured data and AI-search (AEO) helpers.
 *
 * - Page titles (50–60 chars) and meta descriptions (120–160 chars).
 * - Open Graph + Twitter Card tags with a share image.
 * - JSON-LD: MedicalClinic, WebSite, WebPage (with dates), BreadcrumbList,
 *   Person (doctors), Service (services), FAQPage.
 * - Markdown copy of each page (?format=md) for AI tools, advertised with rel="alternate".
 * - llms.txt and a Content-Signal line in robots.txt.
 * - The demo stays noindex on purpose (fictional clinic), so the XML sitemap is switched off to
 *   avoid the contradictory "index this / do not index this" signal.
 *
 * @package riverside-health
 */

defined( 'ABSPATH' ) || exit;

/**
 * Title and description per page slug.
 *
 * @return array slug => [ title, description ]
 */
function rh_seo_pages() {
	return array(
		'home'               => array( 'Riverside Family Health – Family Doctors, Pediatrics & Dentistry', 'Family doctors, pediatrics, women\'s health, dentistry and heart care under one roof. See real free times and request an appointment online in a minute.' ),
		'services'           => array( 'Family Medical & Dental Services – Riverside Family Health', 'Primary care, pediatrics, women\'s health, family dentistry, heart health, lab tests, imaging and vaccinations, all in one clinic with same-week visits.' ),
		'doctors'            => array( 'Meet Our Doctors and Dentists – Riverside Family Health', 'Meet our family physicians, pediatrician, women\'s health specialist, dentist and cardiologist. See each doctor\'s services and book with them online.' ),
		'patients'           => array( 'Patient Information & FAQ – Riverside Family Health', 'What to bring to your first visit, insurance and billing, telehealth, and answers to common questions from new and returning patients of our clinic.' ),
		'book-appointment'   => array( 'Book an Appointment Online – Riverside Family Health', 'Choose a service and doctor, see only real free times and send your request in a minute. Reception confirms every request, usually within one working day.' ),
		'contact'            => array( 'Contact Us & Opening Hours – Riverside Family Health', 'Address, opening hours, phone and email for Riverside Family Health. Send us a message or book online; for emergencies always call 911 straight away.' ),
		'privacy-policy'     => array( 'Privacy Policy for Patients – Riverside Family Health', 'How Riverside Family Health handles the details you send through this demo website: what we collect, why, how long we keep it and how to ask for removal.' ),
		'manage-appointment' => array( 'Manage Your Appointment – Riverside Family Health', 'Use your secure one-time link to cancel or change your appointment request at Riverside Family Health. Links expire after seven days for your safety.' ),
	);
}

/**
 * Current page key.
 *
 * @return string
 */
function rh_seo_key() {
	if ( is_front_page() ) {
		return 'home';
	}
	$post = get_queried_object();
	return ( $post instanceof WP_Post && 'page' === $post->post_type ) ? $post->post_name : '';
}

/**
 * Title + description for the current page (null if not one of ours).
 *
 * @return array|null
 */
function rh_seo_meta() {
	$pages = rh_seo_pages();
	$key   = rh_seo_key();
	return $pages[ $key ] ?? null;
}

// Full custom title for our pages.
add_filter(
	'pre_get_document_title',
	function ( $title ) {
		$m = rh_seo_meta();
		return $m ? $m[0] : $title;
	},
	20
);

/**
 * Share image URL.
 *
 * @return string
 */
function rh_seo_image() {
	return get_theme_file_uri( 'assets/images/og-image.jpg' );
}

/**
 * Meta, Open Graph and Twitter tags.
 */
function rh_seo_head() {
	$m = rh_seo_meta();
	if ( ! $m ) {
		return;
	}
	$url   = is_front_page() ? home_url( '/' ) : get_permalink();
	$tags  = array(
		array( 'name', 'description', $m[1] ),
		array( 'property', 'og:type', 'website' ),
		array( 'property', 'og:site_name', 'Riverside Family Health' ),
		array( 'property', 'og:title', $m[0] ),
		array( 'property', 'og:description', $m[1] ),
		array( 'property', 'og:url', $url ),
		array( 'property', 'og:image', rh_seo_image() ),
		array( 'property', 'og:image:width', '1200' ),
		array( 'property', 'og:image:height', '630' ),
		array( 'property', 'og:image:alt', 'Riverside Family Health – demo clinic website' ),
		array( 'property', 'og:locale', 'en_US' ),
		array( 'name', 'twitter:card', 'summary_large_image' ),
		array( 'name', 'twitter:title', $m[0] ),
		array( 'name', 'twitter:description', $m[1] ),
		array( 'name', 'twitter:image', rh_seo_image() ),
	);
	$post = get_queried_object();
	if ( $post instanceof WP_Post ) {
		$tags[] = array( 'property', 'article:published_time', get_post_time( 'c', true, $post ) );
		$tags[] = array( 'property', 'article:modified_time', get_post_modified_time( 'c', true, $post ) );
	}
	foreach ( $tags as $t ) {
		printf( '<meta %s="%s" content="%s">' . "\n", $t[0], esc_attr( $t[1] ), esc_attr( $t[2] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- attribute name is a fixed string.
	}
	printf( '<link rel="alternate" type="text/markdown" href="%s" title="Markdown version">' . "\n", esc_url( add_query_arg( 'format', 'md', $url ) ) );
	echo '<script type="application/ld+json">' . wp_json_encode( rh_seo_schema( $url, $m ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON encoded.
}
add_action( 'wp_head', 'rh_seo_head', 2 );

/**
 * JSON-LD graph for the current page.
 *
 * @param string $url Page URL.
 * @param array  $m   Title/description.
 * @return array
 */
function rh_seo_schema( $url, $m ) {
	$c     = rh_clinic();
	$home  = home_url( '/' );
	$id    = $home . '#clinic';
	$days  = $c['hours_spec'] ?? array(
		array( array( 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday' ), '08:00', '19:00' ),
		array( array( 'Saturday' ), '09:00', '14:00' ),
	);
	$clinic = array(
		'@type'                     => 'MedicalClinic',
		'@id'                       => $id,
		'name'                      => $c['name'],
		'description'               => 'Demo website of a fictional family clinic: family medicine, pediatrics, women\'s health, dentistry and heart health under one roof.',
		'url'                       => $home,
		'image'                     => rh_seo_image(),
		'logo'                      => get_theme_file_uri( 'assets/icon.svg' ),
		'telephone'                 => $c['phone_raw'],
		'email'                     => $c['email'],
		'address'                   => array( '@type' => 'PostalAddress', 'streetAddress' => $c['address'], 'addressLocality' => 'Riverside', 'addressRegion' => 'CA', 'postalCode' => '92501', 'addressCountry' => 'US' ),
		'openingHoursSpecification' => array_map( fn( $d ) => array( '@type' => 'OpeningHoursSpecification', 'dayOfWeek' => $d[0], 'opens' => $d[1], 'closes' => $d[2] ), $days ),
		'medicalSpecialty'          => array( 'PrimaryCare', 'Pediatric', 'Gynecologic', 'Dentistry', 'Cardiovascular' ),
		'isAcceptingNewPatients'    => true,
		'potentialAction'           => array( '@type' => 'ReserveAction', 'target' => home_url( '/book-appointment/' ), 'name' => 'Request an appointment' ),
	);
	$post  = get_queried_object();
	$page  = array(
		'@type'       => 'WebPage',
		'@id'         => $url . '#webpage',
		'url'         => $url,
		'name'        => $m[0],
		'description' => $m[1],
		'isPartOf'    => array( '@id' => $home . '#website' ),
		'about'       => array( '@id' => $id ),
		'inLanguage'  => 'en-US',
	);
	if ( $post instanceof WP_Post ) {
		$page['datePublished'] = get_post_time( 'c', true, $post );
		$page['dateModified']  = get_post_modified_time( 'c', true, $post );
	}
	$graph = array(
		$clinic,
		array( '@type' => 'WebSite', '@id' => $home . '#website', 'url' => $home, 'name' => $c['name'], 'publisher' => array( '@id' => $id ), 'inLanguage' => 'en-US' ),
		$page,
	);
	$key = rh_seo_key();
	if ( 'home' !== $key ) {
		$graph[] = array(
			'@type'           => 'BreadcrumbList',
			'itemListElement' => array(
				array( '@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => $home ),
				array( '@type' => 'ListItem', 'position' => 2, 'name' => preg_replace( '/ – Riverside Family Health$/', '', $m[0] ), 'item' => $url ),
			),
		);
	}
	if ( 'doctors' === $key || 'home' === $key ) {
		foreach ( rh_doctors() as $slug => $d ) {
			$graph[] = array( '@type' => 'Person', '@id' => home_url( '/doctors/' ) . '#dr-' . $slug, 'name' => $d[0], 'honorificSuffix' => $d[1], 'jobTitle' => $d[2], 'description' => $d[4], 'knowsLanguage' => array_map( 'trim', explode( ',', $d[6] ) ), 'worksFor' => array( '@id' => $id ), 'url' => home_url( '/doctors/' ) . '#dr-' . $slug );
		}
	}
	if ( 'services' === $key ) {
		foreach ( rh_services() as $slug => $s ) {
			$graph[] = array( '@type' => 'Service', 'name' => $s[0], 'description' => $s[2], 'serviceType' => $s[0], 'provider' => array( '@id' => $id ), 'url' => home_url( '/services/' ) . '#' . $slug, 'areaServed' => 'Riverside, CA' );
		}
	}
	if ( 'patients' === $key || 'home' === $key ) {
		$faqs    = 'home' === $key ? array_slice( rh_faqs(), 0, 5 ) : rh_faqs();
		$graph[] = array(
			'@type'      => 'FAQPage',
			'@id'        => $url . '#faq',
			'mainEntity' => array_map( fn( $f ) => array( '@type' => 'Question', 'name' => $f[0], 'acceptedAnswer' => array( '@type' => 'Answer', 'text' => $f[1] ) ), $faqs ),
		);
	}
	return array( '@context' => 'https://schema.org', '@graph' => $graph );
}

/**
 * Markdown copy of a page for AI tools (?format=md).
 */
function rh_seo_markdown() {
	if ( ! isset( $_GET['format'] ) || 'md' !== $_GET['format'] || ! rh_seo_meta() ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only.
		return;
	}
	$post = is_front_page() ? get_post( (int) get_option( 'page_on_front' ) ) : get_queried_object();
	$html = $post instanceof WP_Post ? do_blocks( $post->post_content ) : '';
	$html = preg_replace( '#<(script|style|svg|form|noscript)[^>]*>.*?</\1>#is', '', $html );
	$map  = array( '#<h1[^>]*>#i' => "\n# ", '#<h2[^>]*>#i' => "\n## ", '#<h3[^>]*>#i' => "\n### ", '#<li[^>]*>#i' => "\n- ", '#</(p|div|section|h[1-6]|ul|ol|dl|dd|tr)>#i' => "\n", '#<br\s*/?>#i' => "\n" );
	$md   = preg_replace( array_keys( $map ), array_values( $map ), $html );
	$md   = html_entity_decode( wp_strip_all_tags( $md ), ENT_QUOTES, 'UTF-8' );
	$md   = trim( preg_replace( array( '/[ \t]+/', "/\n\s*\n\s*\n+/" ), array( ' ', "\n\n" ), $md ) );
	$m    = rh_seo_meta();
	nocache_headers();
	header( 'Content-Type: text/markdown; charset=utf-8' );
	header( 'X-Robots-Tag: noindex' );
	echo '# ' . $m[0] . "\n\n> " . $m[1] . "\n\n_Demo website: the clinic, people and data are fictional._\n\n" . $md . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plain text response.
	exit;
}
add_action( 'template_redirect', 'rh_seo_markdown', 1 );

/**
 * llms.txt: short, factual summary for AI tools.
 */
function rh_seo_llms() {
	$path = wp_parse_url( home_url( '/llms.txt' ), PHP_URL_PATH );
	if ( strtok( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), '?' ) !== $path ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		return;
	}
	$c   = rh_clinic();
	$out = "# Riverside Family Health\n\n> Demo website of a fictional family clinic (family medicine, pediatrics, women's health, dentistry, heart health). Built by Mouzzam Builds as a portfolio project. The clinic, doctors, phone numbers and emails are fictional.\n\n";
	$out .= "## Key facts\n\n- Address: {$c['address']}, {$c['city']} (fictional)\n- Hours: " . implode( '; ', array_map( fn( $h ) => $h[0] . ' ' . $h[1], $c['hours'] ) ) . "\n- Booking: online requests show only real free times; reception confirms each request\n\n## Pages\n\n";
	foreach ( rh_seo_pages() as $slug => $m ) {
		if ( 'manage-appointment' === $slug ) {
			continue;
		}
		$u    = 'home' === $slug ? home_url( '/' ) : home_url( '/' . $slug . '/' );
		$out .= '- [' . preg_replace( '/ – Riverside Family Health$/', '', $m[0] ) . '](' . add_query_arg( 'format', 'md', $u ) . '): ' . $m[1] . "\n";
	}
	$out .= "\n## Services\n\n";
	foreach ( rh_services() as $s ) {
		$out .= '- ' . $s[0] . ': ' . $s[2] . "\n";
	}
	$out .= "\n## Doctors\n\n";
	foreach ( rh_doctors() as $d ) {
		$out .= '- ' . $d[0] . ', ' . $d[1] . ' – ' . $d[2] . "\n";
	}
	status_header( 200 );
	header( 'Content-Type: text/plain; charset=utf-8' );
	echo $out; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plain text.
	exit;
}
add_action( 'parse_request', 'rh_seo_llms', 0 );

// Demo stays noindex: no XML sitemap (it would ask search engines to index noindex pages).
add_filter( 'wp_sitemaps_enabled', '__return_false' );

// robots.txt: state how AI may use the content.
add_filter(
	'robots_txt',
	function ( $txt ) {
		return trim( $txt ) . "\n\n# How AI systems may use this content (see contentsignals.org)\nContent-Signal: search=yes, ai-input=yes, ai-train=no\n";
	},
	99
);
