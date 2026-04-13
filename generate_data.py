import json
import datetime
import pytz

# Base date: November 3, 2025, Timezone: Asia/Jakarta
tz = pytz.timezone('Asia/Jakarta')

def time_to_ms(time_str):
    if not time_str or time_str == '-':
        return None
    time_obj = datetime.datetime.strptime(f"2025-11-03 {time_str}", "%Y-%m-%d %H:%M:%S")
    time_obj = tz.localize(time_obj)
    return int(time_obj.timestamp() * 1000)

data_text = """
1|08:00:00|08:00:00|08:03:00|08:03:00|08:05:40|Tidak Melanjutkan|-|-|-|-|-
2|08:05:00|08:08:00|08:11:00|08:11:00|08:13:02|Tidak Melanjutkan|-|-|-|-|-
3|08:10:14|08:11:00|08:13:00|08:13:02|08:18:38|Tidak Melanjutkan|-|-|-|-|-
4|08:11:21|08:13:00|08:15:00|08:18:38|08:20:10|Melanjutkan|08:20:10|08:25:02|Tidak Lolos|-|-
5|08:12:47|08:15:00|08:16:22|08:20:10|08:21:47|Melanjutkan|08:25:02|08:34:34|Tidak Lolos|-|-
6|08:14:12|08:16:22|08:18:01|08:21:47|08:22:53|Melanjutkan|08:34:34|08:52:13|Tidak Lolos|-|-
7|08:14:59|08:18:01|08:20:02|08:22:53|08:23:43|Melanjutkan|08:52:13|09:06:06|Tidak Lolos|-|-
8|08:27:00|08:27:00|08:28:25|08:28:25|08:30:22|Tidak Melanjutkan|-|-|-|-|-
9|08:28:43|08:28:43|08:30:26|08:30:26|08:31:43|Melanjutkan|09:06:06|09:10:23|Tidak Lolos|-|-
10|08:29:24|08:30:26|08:32:00|08:32:00|08:38:00|Melanjutkan|09:10:23|09:25:20|Lolos|09:25:20|09:28:12
11|08:39:06|08:39:06|08:40:21|08:40:21|08:43:10|Melanjutkan|09:25:20|09:32:55|Lolos|09:28:12|09:31:49
12|08:40:01|08:40:21|08:42:11|08:43:10|08:46:48|Tidak Melanjutkan|-|-|-|-|-
13|08:41:54|08:42:11|08:43:30|08:46:48|08:48:42|Melanjutkan|09:32:55|09:36:23|Tidak Lolos|-|-
14|08:43:22|08:43:30|08:48:24|08:48:42|08:53:03|Melanjutkan|09:36:23|09:52:21|Lolos|09:52:21|10:00:14
15|08:49:45|08:49:45|08:50:48|08:53:03|08:56:03|Melanjutkan|09:52:21|09:57:56|Tidak Lolos|-|-
16|08:57:18|08:57:18|08:58:32|08:58:32|09:00:00|Melanjutkan|09:57:56|10:14:09|Lolos|10:14:09|10:20:17
17|08:57:56|08:58:32|08:59:54|09:00:00|09:02:17|Melanjutkan|10:14:09|10:20:46|Tidak Lolos|-|-
18|08:59:00|08:59:54|09:01:55|09:02:17|09:05:06|Melanjutkan|10:20:46|10:41:12|Lolos|10:41:12|10:50:39
19|09:01:22|09:01:55|09:02:39|09:05:06|09:07:21|Tidak Melanjutkan|-|-|-|-|-
20|09:01:34|09:02:39|09:03:20|09:07:21|09:08:59|Melanjutkan|10:41:12|10:50:49|Lolos|10:50:49|10:52:34
21|09:04:06|09:04:06|09:05:00|09:08:59|09:11:00|Tidak Melanjutkan|-|-|-|-|-
22|09:04:50|09:05:00|09:06:14|09:11:00|09:14:11|Tidak Melanjutkan|-|-|-|-|-
23|09:11:00|09:11:00|09:12:06|09:14:11|09:16:07|Melanjutkan|10:50:49|10:54:17|Tidak Lolos|-|-
24|09:12:08|09:12:08|09:13:00|09:16:07|09:19:10|Tidak Melanjutkan|-|-|-|-|-
25|09:12:36|09:13:00|09:14:02|09:19:10|09:20:50|Melanjutkan|10:54:17|10:58:59|Tidak Lolos|-|-
26|09:32:03|09:32:03|09:34:18|09:34:18|09:36:42|Melanjutkan|10:58:59|11:10:10|Lolos|11:10:10|11:17:15
27|09:39:18|09:39:18|09:40:33|09:40:33|09:42:05|Melanjutkan|11:10:10|11:21:49|Lolos|11:21:49|11:25:12
28|09:44:00|09:44:00|09:45:00|09:45:00|09:48:06|Melanjutkan|11:21:49|11:32:01|Lolos|11:32:01|11:38:31
29|09:44:22|09:45:00|09:46:14|09:48:06|09:50:12|Melanjutkan|11:32:01|11:39:14|Lolos|11:39:14|11:43:51
30|09:46:03|09:46:14|09:48:06|09:50:12|09:51:57|Melanjutkan|11:39:14|11:47:16|Lolos|11:47:16|11:50:32
31|09:50:20|09:50:20|09:52:00|09:52:00|09:54:09|Melanjutkan|11:47:16|11:54:28|Tidak Lolos|-|-
32|09:51:09|09:52:00|09:54:34|09:54:34|09:56:43|Tidak Melanjutkan|-|-|-|-|-
33|09:54:08|09:54:34|09:56:00|09:56:00|09:57:55|Melanjutkan|11:54:28|12:01:42|Lolos|12:01:42|12:09:18
34|09:54:08|09:56:00|09:57:00|09:57:55|10:01:10|Melanjutkan|12:01:42|12:06:38|Tidak Lolos|-|-
35|09:59:12|09:59:12|10:01:03|10:01:10|10:04:00|Melanjutkan|12:06:38|12:12:31|Lolos|12:12:31|12:14:48
36|10:08:10|10:08:10|10:10:09|10:10:09|10:12:56|Melanjutkan|12:12:31|12:18:34|Lolos|12:18:34|12:28:12
37|10:16:12|10:16:12|10:18:04|10:18:04|10:20:14|Melanjutkan|12:18:34|12:24:23|Lolos|12:28:12|12:39:01
38|10:17:25|10:18:04|10:19:01|10:20:14|10:23:34|Melanjutkan|12:24:23|12:32:10|Lolos|12:39:01|12:44:58
39|10:28:58|10:28:58|10:29:59|10:29:59|10:32:47|Melanjutkan|12:32:10|12:40:20|Lolos|12:44:58|12:49:24
40|10:57:56|10:57:56|10:59:00|10:59:00|11:01:05|Melanjutkan|12:40:20|12:51:56|Lolos|12:51:56|12:56:44
"""

results = []
for line in data_text.strip().split('\n'):
    parts = line.split('|')
    idx = int(parts[0])
    arrival = parts[1]
    t1_s, t1_e = parts[2], parts[3]
    t2_s, t2_e, t2_status = parts[4], parts[5], parts[6]
    t3_s, t3_e, t3_status = parts[7], parts[8], parts[9]
    t4_s, t4_e = parts[10], parts[11]

    history = []
    
    # Stage 1
    h1 = {
        "stage": 1,
        "masuk_queue": time_to_ms(arrival),
        "masuk_stage": time_to_ms(t1_s),
        "keluar_stage": time_to_ms(t1_e)
    }
    history.append(h1)

    # Stage 2
    h2 = {
        "stage": 2,
        "masuk_queue": time_to_ms(t1_e),
        "masuk_stage": time_to_ms(t2_s),
        "keluar_stage": time_to_ms(t2_e)
    }
    history.append(h2)

    selesai = time_to_ms(t2_e)

    # Stage 3
    if t2_status == "Melanjutkan" and t3_s != '-':
        h3 = {
            "stage": 3,
            "masuk_queue": time_to_ms(t2_e),
            "masuk_stage": time_to_ms(t3_s),
            "keluar_stage": time_to_ms(t3_e)
        }
        history.append(h3)
        selesai = time_to_ms(t3_e)

        # Stage 4
        if t3_status == "Lolos" and t4_s != '-':
            h4 = {
                "stage": 4,
                "masuk_queue": time_to_ms(t3_e),
                "masuk_stage": time_to_ms(t4_s),
                "keluar_stage": time_to_ms(t4_e)
            }
            history.append(h4)
            selesai = time_to_ms(t4_e)

    user = {
        "id": idx,
        "selesai": selesai,
        "history": history,
        "session_id": 1
    }
    results.append(user)

with open('c:/laragon/www/Antrean/results.json', 'w') as f:
    json.dump(results, f, indent=4)
print("Data written to results.json")
