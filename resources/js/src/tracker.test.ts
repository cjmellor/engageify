import { beforeEach, expect, mock, test } from "bun:test";
import { startImpressionTracking } from "./tracker";

const ATTRIBUTE = "data-engageify-impression";

function fakeElement(token: string | null): Element {
    return {
        getAttribute: (name: string): string | null => (name === ATTRIBUTE ? token : null),
    } as unknown as Element;
}

const entry = (target: Element, ratio: number): IntersectionObserverEntry =>
    ({ target, isIntersecting: ratio > 0, intersectionRatio: ratio }) as IntersectionObserverEntry;

let intersectionCallback: IntersectionObserverCallback;

class FakeIntersectionObserver {
    constructor(callback: IntersectionObserverCallback) {
        intersectionCallback = callback;
    }
    observe(): void {}
    unobserve(): void {}
    disconnect(): void {}
}

let marked: Element[];
let fetchMock: ReturnType<typeof mock>;

beforeEach(() => {
    marked = [fakeElement("token-abc")];
    (globalThis as unknown as { IntersectionObserver: unknown }).IntersectionObserver = FakeIntersectionObserver;
    (globalThis as unknown as { document: unknown }).document = {
        querySelectorAll: (): Element[] => marked,
    };
    fetchMock = mock(() => Promise.resolve(new Response(null, { status: 204 })));
    (globalThis as unknown as { fetch: unknown }).fetch = fetchMock;
});

test("counts one impression after the element dwells in view", async () => {
    startImpressionTracking({ endpoint: "/engageify/impressions", threshold: 0.5, dwell: 10 });

    intersectionCallback([entry(marked[0]!, 0.6)], {} as IntersectionObserver);

    expect(fetchMock).not.toHaveBeenCalled();

    await Bun.sleep(40);

    expect(fetchMock).toHaveBeenCalledTimes(1);

    const body = JSON.parse((fetchMock.mock.calls[0]?.[1] as RequestInit).body as string);
    expect(body.token).toBe("token-abc");
});

test("a fast scroll-past does not count", async () => {
    startImpressionTracking({ endpoint: "/engageify/impressions", threshold: 0.5, dwell: 50 });

    intersectionCallback([entry(marked[0]!, 0.6)], {} as IntersectionObserver);
    await Bun.sleep(10);
    intersectionCallback([entry(marked[0]!, 0.0)], {} as IntersectionObserver);
    await Bun.sleep(70);

    expect(fetchMock).not.toHaveBeenCalled();
});

test("counts at most once even with repeated intersections", async () => {
    startImpressionTracking({ endpoint: "/engageify/impressions", threshold: 0.5, dwell: 10 });

    intersectionCallback([entry(marked[0]!, 0.6)], {} as IntersectionObserver);
    await Bun.sleep(40);
    intersectionCallback([entry(marked[0]!, 0.6)], {} as IntersectionObserver);
    await Bun.sleep(40);

    expect(fetchMock).toHaveBeenCalledTimes(1);
});

test("a below-threshold sighting does not start the dwell", async () => {
    startImpressionTracking({ endpoint: "/engageify/impressions", threshold: 0.5, dwell: 10 });

    intersectionCallback([entry(marked[0]!, 0.2)], {} as IntersectionObserver);
    await Bun.sleep(40);

    expect(fetchMock).not.toHaveBeenCalled();
});
