import { Client, PrivateKey, TopicCreateTransaction } from "@hashgraph/sdk";
import "dotenv/config";

const client = Client.forTestnet();
client.setOperator(
  process.env.HEDERA_ACCOUNT_ID,
  PrivateKey.fromStringECDSA(process.env.HEDERA_PRIVATE_KEY)
);

const tx = await new TopicCreateTransaction()
  .setTopicMemo("Insaf - verifiable placement")
  .execute(client);
const receipt = await tx.getReceipt(client);
console.log("TOPIC ID:", receipt.topicId.toString());
process.exit(0);