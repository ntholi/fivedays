import { NextResponse } from 'next/server';
import OpenAI from 'openai';

const openai = new OpenAI({
  organization: process.env.OPENAI_ORG_ID,
  apiKey: process.env.OPENAI_API_KEY,
  timeout: 1000 * 60 * 1, // 1 minute
});

export async function POST(request: Request) {
  const { courseworkTitle, courseName } = await request.json();

  console.log(`Creating ${courseworkTitle} coursework for ${courseName}...`);
  const chatCompletion = await openai.chat.completions.create({
    messages: [
      {
        role: 'user',
        content: `Create assignment description for ${courseName} titled ${courseworkTitle}
        respond in this format {
          "description": "<description>",
        }
        `,
      },
    ],
    model: 'gpt-3.5-turbo',
  });

  console.log(chatCompletion);

  const { content } = chatCompletion.choices[0].message;
  console.log(content);

  return NextResponse.json(JSON.parse(content || ''));
}
