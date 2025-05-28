from flask import Flask, request, jsonify
from vypocet import vypocet_RK

app = Flask(__name__)

@app.route("/vypocet", methods=["POST"])
def vypocet():
    data = request.json
    rrk = int(data["paramtr33"])
    parametryMRK = list(map(int, data["parametryMRK"]))
    parametryMax = list(map(int, data["parametryMax"]))

    parametry = {33: rrk, **{i+1: parametryMRK[i] for i in range(12)}, **{i+30: parametryMax[i] for i in range(12)}}

    min_naklady = vypocet_RK(rrk, parametry)
    uspora = 1_500_000 - min_naklady  # Příkladová úspora

    return jsonify({"min_naklady": min_naklady, "uspora": uspora})

if __name__ == "__main__":
    app.run(debug=True)
