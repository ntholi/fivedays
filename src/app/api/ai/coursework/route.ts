import { getErrorMessage } from '@/lib/common';
import { NextResponse } from 'next/server';
import OpenAI, { OpenAIError } from 'openai';

const openai = new OpenAI({
  organization: process.env.OPENAI_ORG_ID,
  apiKey: process.env.OPENAI_API_KEY,
  timeout: 1000 * 60 * 1, // 1 minute
});

type RequestType = {
  courseworkTitle: string;
  courseName: string;
};

export async function POST(request: Request) {
  const data = (await request.json()) as RequestType;

  console.log(`Creating coursework for ${data}`);

  try {
    const chatCompletion = await openai.chat.completions.create({
      messages: [
        {
          role: 'system',
          content: `You will generate course work based on the provided json payload, and will respond in json using format: { "description": "<description>" }`,
        },
        {
          role: 'user',
          content: JSON.stringify(data),
        },
      ],
      model: 'gpt-3.5-turbo',
    });

    const { content } = chatCompletion.choices[0].message;
    const json = content?.replace(/"/g, `\"`).replace(/'/g, `\'`) || '{}';

    return NextResponse.json(JSON.parse(json));
  } catch (error: any) {
    console.error(error);
    return NextResponse.json(
      { error: getErrorMessage(error) },
      { status: 500 }
    );
  }
}
