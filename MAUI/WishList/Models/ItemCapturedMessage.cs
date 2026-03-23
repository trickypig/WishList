using CommunityToolkit.Mvvm.Messaging.Messages;

namespace WishList.Models;

public class ItemCapturedMessage : ValueChangedMessage<ScrapeResult>
{
    public ItemCapturedMessage(ScrapeResult value) : base(value) { }
}
