Feature: Run the optimizer via the command line

  Scenario: Optimize an input file
    Given a WP installation with the AMP plugin
    And an input.html file:
      """
      <amp-img src="https://example.com/image.jpg" width="500" height="500"></amp-img>
      """

    When I run `wp amp optimizer optimize input.html`
    Then STDERR should be empty
    And STDOUT should contain:
      """
      transformed="self;v=1"
      """