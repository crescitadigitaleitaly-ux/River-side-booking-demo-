<?php
/**
 * Demo content: clinic details, services and doctors.
 *
 * Everything here is fictional and written for a demonstration website.
 * Phone numbers use the reserved 555-01xx range and the email uses the
 * reserved .example domain, so nothing points at a real person or practice.
 *
 * @package riverside-health
 */

defined( 'ABSPATH' ) || exit;

/**
 * Clinic contact details and opening hours.
 *
 * @return array
 */
function rh_clinic() {
	return apply_filters( 'rh_clinic', array(
		'name'      => 'Riverside Family Health',
		'phone'     => '(555) 014-2200',
		'phone_raw' => '+15550142200',
		'urgent'    => '(555) 014-2299',
		'email'     => 'care@riversidefamilyhealth.example',
		'address'   => '1200 Riverbend Avenue, Suite 100',
		'city'      => 'Riverside, CA 92501',
		'maps'      => 'https://www.google.com/maps/search/?api=1&query=1200+Riverbend+Avenue+Riverside+CA',
		'hours'     => array(
			array( 'Monday – Friday', '8:00 am – 7:00 pm' ),
			array( 'Saturday', '9:00 am – 2:00 pm' ),
			array( 'Sunday', 'Closed' ),
		),
	) );
}

/**
 * Services. Keys are used as anchors on the Services page and as values in
 * the booking form.
 *
 * @return array key => [ name, icon, short, long, bullets, image|null ]
 */
function rh_services() {
	return apply_filters( 'rh_services', array(
		'primary-care' => array(
			'Primary Care',
			'stethoscope',
			'Check-ups, chronic condition care and same-week sick visits for adults.',
			'Your family doctor for everyday health: annual physicals, blood pressure and diabetes management, minor illnesses and referrals when you need a specialist.',
			array( 'Annual physicals and wellness exams', 'Diabetes, blood pressure and cholesterol care', 'Same-week sick visits', 'Coordinated specialist referrals' ),
			'svc-primary',
		),
		'pediatrics'   => array(
			'Pediatrics',
			'child',
			'Gentle care for babies, children and teens, from newborn visits to sports physicals.',
			'Growth and development checks, childhood vaccinations, school and sports physicals, and a calm, child-friendly approach for every visit.',
			array( 'Newborn and well-child visits', 'Childhood immunizations', 'School and sports physicals', 'Advice for parents, by phone or in person' ),
			'svc-pediatrics',
		),
		'womens-health' => array(
			"Women's Health",
			'heart-pulse',
			'Preventive screenings, family planning and care through every stage of life.',
			'Routine gynecological exams, cervical and breast screening, contraception counselling, pregnancy planning and menopause care with an experienced, supportive team.',
			array( 'Well-woman exams and screenings', 'Contraception and family planning', 'Prenatal planning and referrals', 'Menopause support' ),
			'svc-womens',
		),
		'dentistry'    => array(
			'Family Dentistry',
			'tooth',
			'Cleanings, fillings and cosmetic treatments for the whole family.',
			'Preventive cleanings, digital X-rays, fillings, crowns and teeth whitening in modern treatment rooms, with clear treatment plans and costs before we begin.',
			array( 'Check-ups and professional cleanings', 'Fillings, crowns and root canal care', 'Teeth whitening', "Children's dentistry" ),
			'svc-dental',
		),
		'cardiology'   => array(
			'Heart Health',
			'heart',
			'Cardiac check-ups, ECGs and long-term care for heart conditions.',
			'Heart health assessments, in-clinic ECGs, blood pressure monitoring and lifestyle programmes to lower your cardiovascular risk, in partnership with your family doctor.',
			array( 'Cardiac risk assessments', 'In-clinic ECG', '24-hour blood pressure monitoring', 'Heart-healthy lifestyle plans' ),
			'svc-heart',
		),
		'diagnostics'  => array(
			'Lab & Diagnostics',
			'flask',
			'On-site blood tests and quick results, usually within 48 hours.',
			'Our on-site laboratory handles routine blood work, urine tests and screenings, so you can often have tests taken during the same visit as your appointment.',
			array( 'Blood tests and routine panels', 'Results usually within 48 hours', 'Screenings for diabetes and cholesterol', 'Results explained by your doctor' ),
			'svc-lab',
		),
		'imaging'      => array(
			'Imaging',
			'scan',
			'Digital X-ray and ultrasound in the same building as your doctor.',
			'Low-dose digital X-ray and ultrasound with images shared directly with your physician, so you do not need to visit another facility for routine imaging.',
			array( 'Low-dose digital X-ray', 'Ultrasound', 'Images shared with your doctor the same day', 'Referrals for MRI and CT when needed' ),
			'svc-imaging',
		),
		'vaccinations' => array(
			'Vaccinations & Travel',
			'syringe',
			'Seasonal flu shots, routine vaccines and travel health advice.',
			'Annual flu vaccination, routine adult and childhood immunizations, and travel consultations with advice tailored to your destination.',
			array( 'Seasonal flu vaccination', 'Routine adult immunizations', 'Travel health consultations', 'Vaccination records on request' ),
			'svc-vaccines',
		),
	) );
}

/**
 * Doctors. Photos are from Unsplash (free licence); names, credentials and
 * biographies are fictional.
 *
 * @return array key => [ name, title, specialty, service key, bio, education, languages ]
 */
function rh_doctors() {
	return apply_filters( 'rh_doctors', array(
		'carter'  => array( 'Dr. Emily Carter', 'MD', 'Family Medicine · Medical Director', 'primary-care', 'Dr. Carter leads the clinic and believes good care starts with listening. She looks after patients of all ages and has a special interest in preventive care and diabetes management.', 'MD, University of California · Residency in Family Medicine', 'English, Spanish' ),
		'hayes'   => array( 'Dr. Daniel Hayes', 'MD', 'Internal Medicine', 'primary-care', 'Dr. Hayes cares for adults with long-term conditions such as hypertension, asthma and high cholesterol, and coordinates closely with specialists when more support is needed.', 'MD, Johns Valley School of Medicine · Residency in Internal Medicine', 'English' ),
		'raman'   => array( 'Dr. Priya Raman', 'MD', "Women's Health", 'womens-health', "Dr. Raman provides gynecological care, screenings and family planning, and is known for taking the time to explain every option clearly.", 'MD, Northfield Medical College · Residency in Obstetrics & Gynecology', 'English, Hindi, Tamil' ),
		'bennett' => array( 'Dr. Marcus Bennett', 'DO', 'Pediatrics', 'pediatrics', 'Dr. Bennett has cared for children for more than a decade. Parents appreciate his calm manner and practical advice, from newborn visits to teenage sports physicals.', 'DO, Western College of Osteopathic Medicine · Residency in Pediatrics', 'English, French' ),
		'romano'  => array( 'Dr. Sofia Romano', 'DDS', 'Family & Cosmetic Dentistry', 'dentistry', 'Dr. Romano makes dental visits comfortable for adults and children alike, with a focus on prevention, gentle treatment and natural-looking cosmetic results.', 'DDS, Pacific School of Dentistry', 'English, Italian' ),
		'okafor'  => array( 'Dr. Grace Okafor', 'MD', 'Cardiology · Heart Health', 'cardiology', 'Dr. Okafor helps patients understand and lower their heart risk, combining in-clinic testing with realistic lifestyle plans and long-term follow-up.', 'MD, Eastbrook University · Fellowship in Cardiology', 'English, Yoruba' ),
	) );
}

/**
 * Frequently asked questions shown on the home and Patients pages.
 *
 * @return array
 */
function rh_faqs() {
	return array(
		array( 'Are you accepting new patients?', 'Yes. New patients of all ages are welcome. Book online or call us, and we will help you transfer your records from your previous practice.' ),
		array( 'Which insurance plans do you accept?', 'We accept Medicare, Medicaid and most major private insurance plans. Call us before your visit and we will confirm your coverage.' ),
		array( 'How quickly can I get an appointment?', 'Most patients are seen within the same week. Same-day appointments are reserved every morning for urgent but non-emergency problems.' ),
		array( 'What should I bring to my first visit?', 'Please bring a photo ID, your insurance card, a list of current medications and any recent test results. Arrive 15 minutes early to complete your registration.' ),
		array( 'Do you offer telehealth appointments?', 'Yes. Many follow-up visits, prescription renewals and test result discussions can be done by secure video call.' ),
		array( 'What should I do in an emergency?', 'If you have a medical emergency, call 911 or go to the nearest emergency department. Do not use the online booking form for urgent problems.' ),
	);
}
