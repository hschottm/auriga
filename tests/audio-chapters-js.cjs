const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = fs.readFileSync(__dirname + '/../src/Resources/public/js/audio-chapters.js', 'utf8');
function element() {
    return {dataset: {}, events: {}, attrs: {}, addEventListener(name, cb) { this.events[name] = cb; }, setAttribute(k,v) {this.attrs[k]=v;}, removeAttribute(k) {delete this.attrs[k];}};
}
const audio = Object.assign(element(), {currentTime: 0, duration: NaN, readyState: 0, paused: true, load() {this.loaded = true;}});
const items = [0,195,494,888].map((time,i) => Object.assign(element(), {dataset: {chapterStart: String(time)}, querySelector() {return {textContent: 'Part '+i};}}));
const previous = element(), next = element(), current = element(), controls = element();
const root = Object.assign(element(), {querySelector(selector) {return {'audio':audio,'[data-chapter-previous]':previous,'[data-chapter-next]':next,'[data-chapter-current]':current,'.auriga-audio-chapters':controls}[selector];}, querySelectorAll() {return items;}});
vm.runInNewContext(source, {document: {readyState:'complete',querySelectorAll() {return [root];}}});
assert.equal(previous.disabled,true);
next.events.click();
assert.equal(audio.loaded,true);
audio.readyState = 1; audio.duration = 1000; audio.events.loadedmetadata();
assert.equal(audio.currentTime,195);
assert.equal(audio.paused,true);
audio.paused = false; audio.currentTime = 520; audio.events.timeupdate();
next.events.click(); assert.equal(audio.currentTime,888); assert.equal(audio.paused,false);
assert.equal(next.disabled,true);
previous.events.click(); assert.equal(audio.currentTime,494);
items[0].events.click(); assert.equal(audio.currentTime,0); assert.equal(previous.disabled,true);
audio.duration = 500; audio.events.durationchange(); assert.equal(items[3].disabled,true);
assert.equal(items[0].attrs['aria-current'],'true');
console.log('OK: deferred seek, previous/next, direct chapter selection, boundaries and playback state');
