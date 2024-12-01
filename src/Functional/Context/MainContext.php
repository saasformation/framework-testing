<?php

namespace SaaSFormation\Framework\Testing\Functional\Context;

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\PyStringNode;
use PHPUnit\Framework\Assert;
use Psr\Http\Message\ResponseInterface;
use React\Http\Message\ServerRequest;
use SaaSFormation\Framework\Contracts\Infrastructure\API\RequestProcessorInterface;
use SaaSFormation\Framework\Contracts\Infrastructure\KernelInterface;
use SaaSFormation\Framework\Projects\Infrastructure\API\DefaultRequestErrorProcessor;
use SaaSFormation\Framework\Projects\Infrastructure\API\DefaultRequestProcessor;
use SaaSFormation\Framework\Projects\Infrastructure\API\LeagueRouterProvider;

final class MainContext implements Context
{
    /** @var array<string, string> */
    private const array PLACEHOLDERS = [
        '$$placeholder$$' => '/^(.*?)$/',
        '$$integer$$' => '/^\d*?$/'
    ];
    private ?ResponseInterface $response;
    /** @var array<string, string> */
    private array $headers = [];

    private RequestProcessorInterface $requestProcessor;

    public function __construct(private readonly KernelInterface $kernel)
    {
        $this->requestProcessor = new DefaultRequestProcessor(
            (new LeagueRouterProvider())->provide($this->kernel->container()),
            new DefaultRequestErrorProcessor($this->kernel->logger())
        );
    }

    /**
     * @afterScenario
     */
    public function afterScenario(): void
    {
        $this->response = null;
    }

    /**
     * @Given /^I call "([^"]*)" "([^"]*)"$/
     *
     * @throws \Exception
     */
    public function iCall(string $verb, string $path): void
    {
        $this->iCallWithBody($verb, $path);
    }

    /**
     * @Then /^I call "([^"]*)" "([^"]*)" with body:$/
     *
     * @throws \Exception
     */
    public function iCallWithBody(string $verb, string $path, ?PyStringNode $string = null): void
    {
        $request = new ServerRequest(
            $verb,
            $path,
            array_merge($this->headers, [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ]),
            $string ? $string->getRaw() : ""
        );

        $this->response = $this->requestProcessor->processRequest($request);
    }

    /**
     * @Then /^the status code should be (\d+)$/
     */
    public function theStatusCodeShouldBe(int $statusCode): void
    {
        if (!$this->response) {
            throw new \Exception("Response must not be null at this point");
        }

        Assert::assertEquals($statusCode, $this->response->getStatusCode());
    }

    /**
     * @Given /^the response should be$/
     */
    public function theResponseShouldBe(PyStringNode $string): void
    {
        if (!$this->response) {
            throw new \Exception("Response must not be null at this point");
        }
        Assert::assertEquals($string->getRaw(), $this->response->getBody()->getContents());
    }

    /**
     * @Given /^the response should be a JSON like$/
     */
    public function theResponseShouldBeAJSONLike(PyStringNode $pattern): void
    {
        if (!$this->response) {
            throw new \Exception("Response must not be null at this point");
        }

        $jsonString = json_encode(json_decode($pattern->getRaw()), JSON_UNESCAPED_UNICODE);
        if (!$jsonString) {
            throw new \Exception("Expected body couldn't be parsed to JSON");
        }

        $expected = str_replace(array_keys(self::PLACEHOLDERS), array_values(self::PLACEHOLDERS), $jsonString);
        $actual = json_encode(json_decode($this->response->getBody()->getContents()), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (!$actual) {
            throw new \Exception("Response body couldn't be parsed to JSON");
        }

        Assert::assertMatchesRegularExpression($expected, $actual);
    }

    /**
     * @Given /^the response should be empty$/
     */
    public function theResponseShouldBeEmpty(): void
    {
        if (!$this->response) {
            throw new \Exception("Response must not be null at this point");
        }

        $expected = '';

        Assert::assertEquals($expected, $this->response->getBody()->getContents());
    }

    /**
     * @Given /^the response should be empty object$/
     */
    public function theResponseShouldBeEmptyObject(): void
    {
        if (!$this->response) {
            throw new \Exception("Response must not be null at this point");
        }

        $expected = '{}';

        Assert::assertEquals($expected, $this->response->getBody()->getContents());
    }
}