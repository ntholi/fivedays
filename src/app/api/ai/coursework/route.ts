import { errorToJSON, toJSON as stringToJSON } from '@/lib/common';
import { completion } from '@/lib/completions';
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

  try {
    const chatCompletion = await openai.chat.completions.create(
      completion({
        task: 'create_coursework',
        input: data,
        responseType: {
          description: 'string',
        },
      })
    );

    const { content } = chatCompletion.choices[0].message;

    return NextResponse.json(stringToJSON(content));
  } catch (error) {
    console.error(error);
    return NextResponse.json(errorToJSON(error));
  }
}
