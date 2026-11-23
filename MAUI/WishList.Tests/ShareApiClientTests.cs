using System.Net;
using System.Text;
using WishList.Models;
using WishList.Share;
using Xunit;

public class ShareApiClientTests
{
    sealed class FakeHandler(int status, string body) : HttpMessageHandler
    {
        public HttpRequestMessage? Last { get; private set; }
        public string LastBody { get; private set; } = string.Empty;

        protected override async Task<HttpResponseMessage> SendAsync(HttpRequestMessage request, CancellationToken ct)
        {
            Last = request;
            LastBody = request.Content is null ? string.Empty : await request.Content.ReadAsStringAsync(ct);
            return new HttpResponseMessage((HttpStatusCode)status)
            {
                Content = new StringContent(body, Encoding.UTF8, "application/json")
            };
        }
    }

    [Fact]
    public async Task GetLists_SendsTokenAndReadsLists()
    {
        var h = new FakeHandler(200, """{"lists":[{"id":7,"title":"Christmas 2026"}]}""");
        var lists = await new ShareApiClient("tok", h).GetListsAsync();
        Assert.Equal("Christmas 2026", Assert.Single(lists).Title);
        Assert.Equal("Bearer tok", h.Last!.Headers.Authorization!.ToString());
        Assert.Equal("tok", Assert.Single(h.Last.Headers.GetValues("X-Auth-Token")));
        Assert.EndsWith("/api/lists", h.Last.RequestUri!.AbsolutePath);
    }

    [Fact]
    public async Task CreateItem_401_ThrowsNotLoggedIn()
    {
        var h = new FakeHandler(401, """{"error":"Token expired"}""");
        await Assert.ThrowsAsync<NotLoggedInException>(() =>
            new ShareApiClient("tok", h).CreateItemAsync(7, new Item { Name = "x" }));
    }

    [Fact]
    public async Task CreateItem_PostsToListWithLinks()
    {
        var h = new FakeHandler(201, """{"item":{"id":1,"name":"x"}}""");
        await new ShareApiClient("tok", h).CreateItemAsync(7, new Item { Name = "x", Links = [new() { Url = "https://a.co/d/x" }] });
        Assert.Equal(HttpMethod.Post, h.Last!.Method);
        Assert.EndsWith("/api/lists/7/items", h.Last.RequestUri!.AbsolutePath);
        Assert.Contains("\"url\":\"https://a.co/d/x\"", h.LastBody);
    }

    [Fact]
    public async Task Scrape_422_ThrowsHttpRequestException()
    {
        var h = new FakeHandler(422, """{"error":"This store blocked the request"}""");
        var ex = await Assert.ThrowsAsync<HttpRequestException>(() =>
            new ShareApiClient("tok", h).ScrapeUrlAsync("https://a.co/d/x"));
        Assert.Contains("blocked", ex.Message);
    }

    sealed class TimeoutHandler : HttpMessageHandler
    {
        protected override Task<HttpResponseMessage> SendAsync(HttpRequestMessage request, CancellationToken ct) =>
            throw new TaskCanceledException("The request was canceled due to the configured HttpClient.Timeout");
    }

    [Fact]
    public async Task Timeout_ThrowsHttpRequestExceptionNotCancellation()
    {
        var ex = await Assert.ThrowsAsync<HttpRequestException>(() =>
            new ShareApiClient("tok", new TimeoutHandler()).GetListsAsync());
        Assert.Equal("Couldn't reach Wish List. Try again.", ex.Message);
    }

    [Fact]
    public async Task CallerCancel_StillThrowsCancellation()
    {
        using var cts = new CancellationTokenSource();
        cts.Cancel();
        await Assert.ThrowsAnyAsync<OperationCanceledException>(() =>
            new ShareApiClient("tok", new TimeoutHandler()).GetListsAsync(cts.Token));
    }

    [Fact]
    public async Task ScrapeHtml_PostsUrlAndHtml()
    {
        var h = new FakeHandler(200, """{"url":"https://x.com/p","name":"Widget","price":9.5}""");
        var r = await new ShareApiClient("tok", h).ScrapeHtmlAsync("https://x.com/p", "<html></html>");
        Assert.Equal("Widget", r.Name);
        Assert.EndsWith("/api/scrape/html", h.Last!.RequestUri!.AbsolutePath);
        using var sent = System.Text.Json.JsonDocument.Parse(h.LastBody);
        Assert.Equal("<html></html>", sent.RootElement.GetProperty("html").GetString());
        Assert.Equal("https://x.com/p", sent.RootElement.GetProperty("url").GetString());
    }
}
