<?php

use Asset_Loader\Admin;

class Test_Admin_Scripts extends AssetLoaderTestCase {
    public function test_maybe_setup_ssl_cert_error_handling_non_admin() : void {
        WP_Mock::userFunction( 'is_admin' )->andReturn( false );
        WP_Mock::expectActionNotAdded( 'admin_head', 'Asset_Loader\\Admin\\render_localhost_error_detection_script' );
        Admin\maybe_setup_ssl_cert_error_handling( 'https://localhost:9000/some-script.js' );

        $this->assertConditionsMet( 'Unexpected admin_head callback detected' );
    }

    public function test_maybe_setup_ssl_cert_error_handling_non_localhost() : void {
        WP_Mock::userFunction( 'is_admin' )->andReturn( true );
        WP_Mock::expectActionNotAdded( 'admin_head', 'Asset_Loader\\Admin\\render_localhost_error_detection_script' );
        Admin\maybe_setup_ssl_cert_error_handling( 'https://some-non-local-domain.com/some-script.js' );

        $this->assertConditionsMet( 'Unexpected admin_head callback detected' );
    }

    public function test_maybe_setup_ssl_cert_error_handling_non_https() : void {
        WP_Mock::userFunction( 'is_admin' )->andReturn( true );
        WP_Mock::expectActionNotAdded( 'admin_head', 'Asset_Loader\\Admin\\render_localhost_error_detection_script' );
        Admin\maybe_setup_ssl_cert_error_handling( 'http://localhost:9000/some-script.js' );

        $this->assertConditionsMet( 'Unexpected admin_head callback detected' );
    }

    public function test_maybe_setup_ssl_cert_error_handling() : void {
        WP_Mock::userFunction( 'is_admin' )->andReturn( true );
        WP_Mock::expectActionAdded( 'admin_head', 'Asset_Loader\\Admin\\render_localhost_error_detection_script', 5 );
        Admin\maybe_setup_ssl_cert_error_handling( 'https://localhost:9000/some-script.js' );

        $this->assertConditionsMet( 'No callback registered for admin_head action' );
    }
}
